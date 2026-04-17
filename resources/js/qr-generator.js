import QRCode from 'qrcode';

const qrInput = document.getElementById('qr-text');
const qrCanvas = document.getElementById('qr-canvas');
const qrContainer = document.getElementById('qr-container');
const noContent = document.getElementById('no-content');
const downloadBtn = document.getElementById('download-btn');
const qrColor = document.getElementById('qr-color');
const qrBg = document.getElementById('qr-bg');

let debounceTimer;

const generateQR = async () => {
    const text = qrInput.value.trim();

    if (!text) {
        qrCanvas.classList.add('hidden');
        noContent.classList.remove('hidden');
        downloadBtn.disabled = true;
        return;
    }

    try {
        await QRCode.toCanvas(qrCanvas, text, {
            width: 300,
            margin: 2,
            color: {
                dark: qrColor.value,
                light: qrBg.value
            },
            errorCorrectionLevel: 'H'
        });

        qrCanvas.classList.remove('hidden');
        noContent.classList.add('hidden');
        downloadBtn.disabled = false;
        
        // Add a nice fade-in animation to the canvas
        qrCanvas.style.opacity = '0';
        setTimeout(() => {
            qrCanvas.style.transition = 'opacity 0.5s ease';
            qrCanvas.style.opacity = '1';
        }, 50);

    } catch (err) {
        console.error('QR Generation failed:', err);
    }
};

// Event Listeners
qrInput.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(generateQR, 300);
});

qrColor.addEventListener('input', generateQR);
qrBg.addEventListener('input', generateQR);

downloadBtn.addEventListener('click', () => {
    const link = document.createElement('a');
    link.download = `qr-code-${Date.now()}.png`;
    link.href = qrCanvas.toDataURL('image/png');
    link.click();
});

// Initial state
downloadBtn.disabled = true;
