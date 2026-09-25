// Accepts H:M:S, M:S or S (seconds), used for editing recorded durations.
export function parseClockDuration(value) {
    const parts = String(value || '').trim().split(':');

    if (parts.length > 3 || parts.some((part) => !/^\d+$/.test(part))) {
        return null;
    }

    return parts.reduce((total, part) => (total * 60) + Number(part), 0);
}

// Accepts HH:MM, H:MM or :MM (hours optional), ignoring any seconds component.
export function parseHourMinuteDuration(value) {
    const parts = String(value || '').trim().split(':');

    if (parts.length > 2) {
        return null;
    }

    if (parts.length === 1) {
        if (!/^\d+$/.test(parts[0])) {
            return null;
        }

        return Number(parts[0]) * 3600;
    }

    const [hoursPart, minutesPart] = parts;

    if (!/^\d*$/.test(hoursPart) || !/^\d+$/.test(minutesPart)) {
        return null;
    }

    const hours = hoursPart === '' ? 0 : Number(hoursPart);

    return (hours * 60 + Number(minutesPart)) * 60;
}

export function formatClockDuration(totalSeconds) {
    const seconds = Math.max(0, Math.floor(Number(totalSeconds || 0)));

    return [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}
