export function timeAgoInVietnamese(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    const diffInMinutes = Math.floor(diffInSeconds / 60);
    const diffInHours = Math.floor(diffInMinutes / 60);
    const diffInDays = Math.floor(diffInHours / 24);
    const diffInWeeks = Math.floor(diffInDays / 7);
    const diffInMonths = Math.floor(diffInDays / 30);
    const diffInYears = Math.floor(diffInDays / 365);

    if (diffInYears > 0) {
        return `${diffInYears} năm trước`;
    } else if (diffInMonths > 0) {
        return `${diffInMonths} tháng trước`;
    } else if (diffInWeeks > 0) {
        return `${diffInWeeks} tuần trước`;
    } else if (diffInDays > 0) {
        return `${diffInDays} ngày trước`;
    } else if (diffInHours > 0) {
        return `${diffInHours} giờ trước`;
    } else if (diffInMinutes > 0) {
        return `${diffInMinutes} phút trước`;
    } else {
        return "Vừa xong";
    }
}
