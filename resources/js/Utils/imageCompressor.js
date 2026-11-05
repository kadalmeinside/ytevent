/**
 * Client-Side Image Compressor for YTEvent
 *
 * Mengompresi gambar di browser pengguna secara otomatis menggunakan HTML5 Canvas
 * sebelum dikirimkan ke server. Mencegah error "file size exceeds 2MB" saat manajer tim
 * mengunggah foto langsung dari kamera smartphone beresolusi tinggi (5 MB - 12 MB).
 *
 * @param {File} file - Berkas File asli dari input file
 * @param {Object} options - Pengaturan kompresi
 * @param {number} options.maxDimension - Maksimum lebar atau tinggi piksel (default: 1600px)
 * @param {number} options.quality - Kualitas kompresi JPEG/WebP (0.1 - 1.0, default: 0.82)
 * @param {number} options.thresholdBytes - Ambang batas ukuran berkas untuk mulai dikompresi (default: 800 KB)
 * @returns {Promise<{file: File, originalSize: number, compressedSize: number, wasCompressed: boolean}>}
 */
export async function compressImage(file, options = {}) {
    if (!file || !file.type.startsWith('image/')) {
        return { file, originalSize: file?.size || 0, compressedSize: file?.size || 0, wasCompressed: false };
    }

    // Hindari mengompres SVG atau format non-raster
    if (file.type === 'image/svg+xml') {
        return { file, originalSize: file.size, compressedSize: file.size, wasCompressed: false };
    }

    const maxDimension = options.maxDimension || 1600;
    const quality = options.quality !== undefined ? options.quality : 0.82;
    const thresholdBytes = options.thresholdBytes || 800 * 1024; // 800 KB

    // Jika file sudah di bawah ambang batas ukuran, kembalikan langsung tanpa membebani CPU browser
    if (file.size <= thresholdBytes) {
        return { file, originalSize: file.size, compressedSize: file.size, wasCompressed: false };
    }

    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                let { width, height } = img;

                // Hitung dimensi baru proporsional jika melebihi batas maksimal
                if (width > maxDimension || height > maxDimension) {
                    if (width > height) {
                        height = Math.round((height * maxDimension) / width);
                        width = maxDimension;
                    } else {
                        width = Math.round((width * maxDimension) / height);
                        height = maxDimension;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d', { alpha: file.type === 'image/png' });
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                // Jika format PNG dengan latar transparan diubah ke JPEG, beri latar belakang putih bersih
                if (file.type !== 'image/png') {
                    ctx.fillStyle = '#FFFFFF';
                    ctx.fillRect(0, 0, width, height);
                }

                ctx.drawImage(img, 0, 0, width, height);

                // Gunakan format JPEG atau WebP untuk kompresi efektif
                const outputType = file.type === 'image/png' ? 'image/png' : 'image/jpeg';
                const outputQuality = file.type === 'image/png' ? undefined : quality;

                canvas.toBlob(
                    (blob) => {
                        if (!blob || blob.size >= file.size) {
                            // Jika kompresi tidak menghasilkan berkas lebih kecil, gunakan berkas asli
                            resolve({ file, originalSize: file.size, compressedSize: file.size, wasCompressed: false });
                            return;
                        }

                        const compressedFile = new File([blob], file.name, {
                            type: outputType,
                            lastModified: Date.now(),
                        });

                        resolve({
                            file: compressedFile,
                            originalSize: file.size,
                            compressedSize: compressedFile.size,
                            wasCompressed: true,
                        });
                    },
                    outputType,
                    outputQuality
                );
            };

            img.onerror = () => {
                resolve({ file, originalSize: file.size, compressedSize: file.size, wasCompressed: false });
            };

            img.src = e.target.result;
        };

        reader.onerror = () => {
            resolve({ file, originalSize: file.size, compressedSize: file.size, wasCompressed: false });
        };

        reader.readAsDataURL(file);
    });
}
