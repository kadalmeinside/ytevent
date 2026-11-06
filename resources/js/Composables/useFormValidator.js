import { nextTick } from 'vue';

export function useFormValidator() {
    const isEmpty = (val) => {
        if (val === null || val === undefined) return true;
        if (typeof val === 'string') return val.trim().length === 0;
        if (Array.isArray(val)) return val.length === 0;
        return false;
    };

    const isValidEmail = (val) => {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(val || '').trim());
    };

    const isValidPassword = (val) => {
        const str = String(val || '');
        return str.length >= 8 && /[A-Za-z]/.test(str) && /\d/.test(str);
    };

    const isValidIndonesianPhone = (val) => {
        const cleaned = String(val || '').replace(/[\s\-]/g, '');
        return /^(\+62|62|0)8[1-9][0-9]{7,12}$/.test(cleaned);
    };

    const isValidNik = (val) => {
        return /^\d{16}$/.test(String(val || '').trim());
    };

    const checkFile = (file, allowedMimes = ['image/png', 'image/jpeg'], maxBytes = 2 * 1024 * 1024) => {
        if (!file) return { valid: false, reason: 'missing' };
        if (file.size > maxBytes) return { valid: false, reason: 'size' };
        if (allowedMimes.length > 0 && !allowedMimes.includes(file.type)) {
            const ext = (file.name || '').split('.').pop()?.toLowerCase();
            const extMap = {
                png: 'image/png',
                jpg: 'image/jpeg',
                jpeg: 'image/jpeg',
                pdf: 'application/pdf',
            };
            if (!ext || !allowedMimes.includes(extMap[ext])) {
                return { valid: false, reason: 'mime' };
            }
        }
        return { valid: true, reason: null };
    };

    const scrollToFirstError = async (errorsObj) => {
        await nextTick();
        const firstKey = Object.keys(errorsObj || {})[0];
        if (!firstKey) return;
        const el =
            document.querySelector(`[data-field="${firstKey}"]`) ||
            document.querySelector(`[name="${firstKey}"]`) ||
            document.getElementById(firstKey);
        if (el && typeof el.scrollIntoView === 'function') {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof el.focus === 'function') {
                el.focus({ preventScroll: true });
            }
        }
    };

    const applyValidation = (form, rulesCallback) => {
        form.clearErrors();
        const errors = rulesCallback() || {};
        const keys = Object.keys(errors);

        if (keys.length > 0) {
            keys.forEach((key) => {
                if (errors[key]) {
                    form.setError(key, errors[key]);
                }
            });
            scrollToFirstError(errors);
            return false;
        }

        return true;
    };

    return {
        isEmpty,
        isValidEmail,
        isEmail: isValidEmail,
        isValidPassword,
        isValidIndonesianPhone,
        isValidNik,
        checkFile,
        scrollToFirstError,
        applyValidation,
    };
}

