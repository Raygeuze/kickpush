export function timeAgo(date) {
  const now = new Date();
  const dateObj = new Date(date);
  const seconds = Math.floor((now - dateObj) / 1000);

  if (seconds < 60) {
    return "just now";
  }

  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) {
    return `${minutes} minute${minutes === 1 ? '' : 's'} ago`;
  }

  const hours = Math.floor(minutes / 60);
  if (hours < 24) {
    return `${hours} hour${hours === 1 ? '' : 's'} ago`;
  }

  const days = Math.floor(hours / 24);
  if (days < 7) {
    return `${days} day${days === 1 ? '' : 's'} ago`;
  }

  // For longer periods, you might want to display the full date
  return dateObj.toLocaleDateString(); 
}

export function formatTimeLeft() {
  const now = new Date();
  const endOfDay = new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth(), now.getUTCDate() + 1, 0, 0, 0));
  const totalSeconds = Math.floor((endOfDay - now) / 1000);

  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60) + 1;
  const seconds = totalSeconds % 60;

  return `${hours.toString().padStart(2, '0')}hr ${minutes.toString().padStart(2, '0')}m`;
}