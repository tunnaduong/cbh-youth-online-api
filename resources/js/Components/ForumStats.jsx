import { Link } from "@inertiajs/react";
import React from "react";
import { NewspaperOutline, ChatboxEllipsesOutline, PersonOutline } from "react-ionicons";

const ForumStats = ({ stats }) => {
  console.log(stats);
  const formatDate = (dateString) => {
    const date = new Date(dateString);
    const weekdays = ["Chủ Nhật", "Thứ Hai", "Thứ Ba", "Thứ Tư", "Thứ Năm", "Thứ Sáu", "Thứ Bảy"];
    const day = date.getDate();
    const month = date.getMonth() + 1;
    const year = date.getFullYear();
    const hours = date.getHours();
    const minutes = date.getMinutes();
    const period = hours >= 12 ? "CH" : "SA";
    const hour12 = hours % 12 || 12;

    return `${weekdays[date.getDay()]}, ngày ${day} tháng ${month} năm ${year}, ${hour12
      .toString()
      .padStart(2, "0")}:${minutes.toString().padStart(2, "0")} ${period}`;
  };

  return (
    <div className="max-w-[775px] mx-auto bg-white dark:!bg-[var(--main-white)] p-6 rounded-lg long-shadow">
      <div className="flex flex-row items-center justify-between mb-4">
        <h2 className="text-lg font-semibold uppercase">Thống kê diễn đàn</h2>
      </div>
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:hover:!bg-[#4a4a4a] hover:bg-green-100 shadow-md rounded-lg p-4 text-center">
          <div className="flex justify-center">
            <NewspaperOutline color={"#319528"} height={"30px"} width={"30px"} />
          </div>
          <h3 className="text-xl font-semibold">{stats.postCount}</h3>
          <p className="text-gray-500">Bài viết</p>
        </div>
        <div className="bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:hover:!bg-[#4a4a4a] hover:bg-green-100 shadow-md rounded-lg p-4 text-center">
          <div className="flex justify-center">
            <ChatboxEllipsesOutline color={"#319528"} height={"30px"} width={"30px"} />
          </div>
          <h3 className="text-xl font-semibold">{stats.commentCount}</h3>
          <p className="text-gray-500">Bình luận</p>
        </div>
        <div className="bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:hover:!bg-[#4a4a4a] hover:bg-green-100 shadow-md rounded-lg p-4 text-center">
          <div className="flex justify-center">
            <PersonOutline color={"#319528"} height={"30px"} width={"30px"} />
          </div>
          <h3 className="text-xl font-semibold">{stats.userCount}</h3>
          <p className="text-gray-500">Người dùng</p>
        </div>
      </div>
      <div className="mt-6">
        <p className="text-gray-600 dark:!text-gray-50">
          Chúng ta cùng chào mừng thành viên mới nhất đã tham gia diễn đàn:
          {stats.latestUser ? (
            <Link
              href={"/" + stats.latestUser.username}
              className="hover:underline font-bold text-green-600 ml-1"
            >
              {stats.latestUser.profile?.profile_name || stats.latestUser.username}
            </Link>
          ) : (
            <span className="font-bold text-green-600 ml-1">Chưa có thành viên</span>
          )}
        </p>
        <p className="text-gray-600 my-2 dark:!text-gray-50">
          Tổng cộng có
          <span className="font-bold text-green-600 ml-1">{stats.visitors.total}</span> người dùng
          trực tuyến:
          <span className="font-semibold ml-1">{stats.visitors.registered}</span> đã đăng ký,
          <span className="font-semibold ml-1">{stats.visitors.hidden}</span> ẩn và
          <span className="font-semibold ml-1">{stats.visitors.guests}</span> khách
        </p>
        <p className="text-gray-600 dark:!text-gray-50">
          Số người dùng trực tuyến nhiều nhất là
          <span className="font-semibold text-green-600 mx-1">{stats.record.max_online}</span>
          vào
          <span className="ml-1">{formatDate(stats.record.recorded_at)}</span>
        </p>
      </div>
    </div>
  );
};

export default ForumStats;
