document.addEventListener('DOMContentLoaded', function () {
    const video = document.getElementById('qr-video');
    const startButton = document.getElementById('start-scanner');
    const stopButton = document.getElementById('stop-scanner');
    const sessionSelect = document.getElementById('session-id');
    const statusElement = document.getElementById('scanner-status');
    const resultElement = document.getElementById('scan-result');

    if (!video || !startButton || !stopButton) {
        return;
    }

    let mediaStream = null;
    let detector = null;
    let scanning = false;
    let processing = false;
    let animationFrame = null;
    let lastToken = '';
    let lastScanTime = 0;

    const duplicateScanDelay = 2500;

    function setStatus(message, type) {
        if (!statusElement) {
            return;
        }

        statusElement.textContent = message;
        statusElement.className = 'scanner-status';

        if (type === 'ready') {
            statusElement.classList.add('is-ready');
        } else if (type === 'scanning') {
            statusElement.classList.add('is-scanning');
        } else if (type === 'error') {
            statusElement.classList.add('is-error');
        }
    }

    function showResult(message, type) {
        if (!resultElement) {
            return;
        }

        resultElement.textContent = message;
        resultElement.className = 'scan-result is-visible';

        if (type === 'success') {
            resultElement.classList.add('is-success');
        } else {
            resultElement.classList.add('is-error');
        }
    }

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');

        if (meta) {
            return meta.getAttribute('content') || '';
        }

        const input = document.querySelector(
            'input[name="csrf_token"]'
        );

        return input ? input.value : '';
    }

    function getSelectedSession() {
        if (!sessionSelect) {
            return '';
        }

        return sessionSelect.value;
    }

    function validateSession() {
        const sessionId = getSelectedSession();

        if (!sessionId || !/^\d+$/.test(sessionId)) {
            showResult('Please select a valid attendance session.', 'error');
            return false;
        }

        return true;
    }

    async function initializeDetector() {
        if (!('BarcodeDetector' in window)) {
            throw new Error(
                'QR detection is not supported by this browser. ' +
                'Load a QR scanning library or use a supported browser.'
            );
        }

        const supportedFormats =
            await window.BarcodeDetector.getSupportedFormats();

        if (!supportedFormats.includes('qr_code')) {
            throw new Error('This browser does not support QR code detection.');
        }

        detector = new window.BarcodeDetector({
            formats: ['qr_code']
        });
    }

    async function startScanner() {
        if (scanning) {
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus(
                'Camera access is unavailable. Use localhost or HTTPS.',
                'error'
            );
            return;
        }

        if (!validateSession()) {
            return;
        }

        try {
            await initializeDetector();

            mediaStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' }
                },
                audio: false
            });

            video.srcObject = mediaStream;
            video.setAttribute('playsinline', 'true');

            await video.play();

            scanning = true;
            startButton.disabled = true;
            stopButton.disabled = false;

            setStatus('Scanner is active. Point the camera at a member QR code.', 'scanning');

            scanFrame();
        } catch (error) {
            stopScanner();

            setStatus(
                error.message || 'Unable to start the camera.',
                'error'
            );
        }
    }

    function stopScanner() {
        scanning = false;

        if (animationFrame !== null) {
            cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }

        if (mediaStream) {
            mediaStream.getTracks().forEach(function (track) {
                track.stop();
            });

            mediaStream = null;
        }

        video.pause();
        video.srcObject = null;

        startButton.disabled = false;
        stopButton.disabled = true;

        setStatus('Scanner stopped.', 'ready');
    }

    async function scanFrame() {
        if (!scanning || !detector || video.readyState < 2) {
            if (scanning) {
                animationFrame = requestAnimationFrame(scanFrame);
            }

            return;
        }

        try {
            const barcodes = await detector.detect(video);

            if (barcodes.length > 0) {
                const rawValue = barcodes[0].rawValue;

                if (rawValue) {
                    await processScannedValue(rawValue);
                }
            }
        } catch (error) {
            console.error('QR detection error:', error);
        }

        if (scanning) {
            animationFrame = requestAnimationFrame(scanFrame);
        }
    }

    /**
     * Supports either a raw token or a URL containing ?token=...
     * Never trust the decoded QR code until the server verifies it.
     */
    function extractToken(value) {
        const trimmed = String(value || '').trim();

        try {
            const url = new URL(trimmed);

            if (url.searchParams.has('token')) {
                return url.searchParams.get('token') || '';
            }
        } catch (error) {
            // The QR data may simply be a token rather than a URL.
        }

        return trimmed;
    }

    async function processScannedValue(value) {
        if (processing) {
            return;
        }

        const token = extractToken(value);

        // The QR token generator uses 64 hexadecimal characters.
        if (!/^[a-f0-9]{64}$/i.test(token)) {
            showResult('This QR code is not a valid member QR token.', 'error');
            return;
        }

        const now = Date.now();

        if (
            token === lastToken &&
            now - lastScanTime < duplicateScanDelay
        ) {
            return;
        }

        lastToken = token;
        lastScanTime = now;
        processing = true;

        try {
            setStatus('Verifying member...', 'scanning');

            const csrfToken = getCsrfToken();

            const verifyResponse = await fetch(
                'api/verify_qr.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        qr_token: token
                    })
                }
            );

            const member = await verifyResponse.json();

            if (!verifyResponse.ok || !member.success) {
                throw new Error(
                    member.message || 'Member verification failed.'
                );
            }

            setStatus('Member verified. Recording attendance...', 'scanning');

            const recordResponse = await fetch(
                'api/record_attendance.php',
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        qr_token: token,
                        session_id: getSelectedSession()
                    })
                }
            );

            const attendance = await recordResponse.json();

            if (!recordResponse.ok || !attendance.success) {
                throw new Error(
                    attendance.message || 'Unable to record attendance.'
                );
            }

            const memberName =
                member.member && member.member.full_name
                    ? member.member.full_name
                    : 'Member';

            showResult(
                'Attendance recorded successfully for ' + memberName + '.',
                'success'
            );

            setStatus('Attendance recorded successfully.', 'ready');

            document.dispatchEvent(
                new CustomEvent('attendance:recorded', {
                    detail: {
                        member: member.member || null,
                        attendance: attendance
                    }
                })
            );
        } catch (error) {
            showResult(error.message || 'An error occurred.', 'error');
            setStatus('Unable to complete the scan.', 'error');
        } finally {
            processing = false;
        }
    }

    startButton.addEventListener('click', startScanner);
    stopButton.addEventListener('click', stopScanner);

    if (sessionSelect) {
        sessionSelect.addEventListener('change', function () {
            if (scanning) {
                stopScanner();
            }

            if (resultElement) {
                resultElement.className = 'scan-result';
                resultElement.textContent = '';
            }

            setStatus('Select a session, then start the scanner.', 'ready');
        });
    }

    window.addEventListener('beforeunload', stopScanner);

    setStatus('Select a session, then start the scanner.', 'ready');
    stopButton.disabled = true;
});