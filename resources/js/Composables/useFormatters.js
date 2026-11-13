export function useFormatters() {
    const formatRupiah = (amount, options = {}) => {
        const num = Number(amount || 0);
        if (num === 0 && options?.showFreeLabel) return 'Gratis (Rp 0)';
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(num);
    };

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (Number.isNaN(date.getTime())) return dateStr;
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }).format(date);
    };

    const formatDateTime = (dateStr) => {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        if (Number.isNaN(date.getTime())) return dateStr;
        return new Intl.DateTimeFormat('id-ID', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        }).format(date);
    };

    const formatBirthDateRange = (minDate, maxDate, emptyLabel = 'Semua Usia (Tanpa Batas)') => {
        if (!minDate && !maxDate) return emptyLabel;
        if (minDate && !maxDate) return `Lahir mulai ${formatDate(minDate)}`;
        if (!minDate && maxDate) return `Lahir maks. ${formatDate(maxDate)}`;
        return `${formatDate(minDate)} s/d ${formatDate(maxDate)}`;
    };

    const formatLabel = (formatCode) => {
        const map = {
            cup: 'Cup (Sistem Gugur)',
            league: 'League (Kompetisi Penuh)',
            group_knockout: 'Group + Knockout',
            festival: 'Grassroots Festival',
        };
        return map[formatCode] || formatCode;
    };

    return {
        formatRupiah,
        formatDate,
        formatDateTime,
        formatBirthDateRange,
        formatLabel,
    };
}

