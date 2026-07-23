/**
 * Client-side OCR for the supervisor internal-request wizard.
 * tesseract.js is loaded on demand (dynamic import) so the main bundle and
 * every other page stay untouched — only the wizard pays the cost, and only
 * when the user actually runs an extraction.
 */
window.runOcr = async function (file, onProgress = () => {}) {
    const { createWorker } = await import('tesseract.js');

    const worker = await createWorker('eng', 1, {
        logger: (m) => {
            if (m.status === 'recognizing text') {
                onProgress(Math.round(m.progress * 100));
            }
        },
    });

    try {
        const { data } = await worker.recognize(file);
        return data.text || '';
    } finally {
        await worker.terminate();
    }
};
