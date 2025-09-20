import { Link, usePage, router } from "@inertiajs/react";
import { AddOutline, HelpCircleOutline } from "react-ionicons";
import { Skeleton } from "antd";
import { useTopUsers } from "../Contexts/TopUsersContext";
import CustomColorButton from "./ui/CustomColorButton";
import { useState } from "react";
import CreatePostModal from "./CreatePostModal";

export default function RightSidebar() {
  const iconSize = "20px";
  const { is_logged_in } = usePage().props;
  const { topUsers, loading, error } = useTopUsers();
  const [open, setOpen] = useState(false);
  return (
    <>
      <CreatePostModal open={open} onClose={() => setOpen(false)} />
      {/* Right side bar */}
      <div className="w-full max-w-[775px] xl:w-[340px] mx-auto !pb-6 xl:p-6" id="right-sidebar">
        <div className="sticky top-[calc(69px+24px)]">
          <CustomColorButton
            bgColor={"#319527"}
            block
            className="text-base text-white font-semibold py-[19px] mb-1.5 hidden xl:flex"
            onClick={() => (is_logged_in ? setOpen(true) : router.visit("/login"))}
          >
            <AddOutline color="#FFFFFF" height={iconSize} width={iconSize} cssClasses="-mr-1" />
            Tạo cuộc thảo luận
          </CustomColorButton>
          <div className="bg-white dark:!bg-[var(--main-white)] text-sm p-3 xl:mt-4 rounded-xl long-shadow [@media(max-width:800px)]:mx-2.5">
            <div className="flex flex-row items-center justify-between">
              <span className="font-bold text-[#6B6B6B] dark:text-neutral-300 block text-base">
                Xếp hạng thành viên
              </span>
              {/* Assuming this is an internal link, if external keep as <a> */}
              <Link href="/Admin/posts/213101">
                {" "}
                {/* Changed to Link */}
                <HelpCircleOutline color="#888888" height={iconSize} width={iconSize} />
              </Link>
            </div>
            {/* User ranking list - dynamic data from API */}
            {loading ? (
              <div className="space-y-2 pt-2">
                {[...Array(8)].map((_, index) => (
                  <div key={index} className="flex flex-row items-center">
                    <Skeleton.Avatar active size={32} className="flex-shrink-0" />
                    <div className="ml-1.5 flex-1">
                      <Skeleton.Input active size="small" style={{ width: "60%", height: 16 }} />
                    </div>
                    <div className="ml-1.5">
                      <Skeleton.Button active size="small" style={{ width: "10%", height: 16 }} />
                    </div>
                  </div>
                ))}
              </div>
            ) : error ? (
              <div className="flex flex-col justify-center items-center py-4">
                <div className="text-red-500 text-sm">Lỗi tải dữ liệu</div>
                <div className="text-gray-400 text-xs mt-1">{error}</div>
                <button
                  onClick={() => window.location.reload()}
                  className="text-blue-500 text-xs mt-2 hover:underline"
                >
                  Thử lại
                </button>
              </div>
            ) : (
              topUsers.map((user, index) => (
                <div key={user.uid} className="flex flex-row items-center mt-2">
                  <Link href={`/${user.username}`}>
                    <img
                      src={
                        user.oauth_profile_picture ||
                        `https://api.chuyenbienhoa.com/v1.0/users/${user.username}/avatar`
                      }
                      className="w-8 h-8 bg-gray-300 rounded-full border object-cover"
                      alt={`${user.profile_name || user.username} avatar`}
                    />
                  </Link>
                  <Link
                    href={`/${user.username}`}
                    className="ml-1.5 font-semibold flex-1 truncate text-left"
                  >
                    {user.profile_name || user.username}
                  </Link>
                  <span className="mr-1.5 text-[#C1C1C1]">{user.total_points} điểm</span>
                  <span className="text-green-500 font-bold">#{index + 1}</span>
                </div>
              ))
            )}
          </div>
          <div className="hidden xl:block">
            <div className="flex flex-row text-sm font-semibold p-3 text-[#BCBCBC] dark:text-neutral-400">
              <div className="flex flex-1 flex-col gap-y-0.5">
                <Link href="/help" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Hỗ trợ
                </Link>
                <Link href="/contact" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Liên hệ
                </Link>
                <Link
                  href="https://stats.uptimerobot.com/i7pA9rBmTC/798634874"
                  className="w-fit hover:text-gray-700 dark:hover:text-white"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Trạng thái
                </Link>
                <Link href="/ads" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Quảng cáo
                </Link>
              </div>
              <div className="flex flex-1 flex-col ml-5 gap-y-0.5">
                <Link href="/about" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Giới thiệu
                </Link>
                <Link href="/careers" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Việc làm
                </Link>
                <Link href="/terms" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Điều khoản
                </Link>
                <Link href="/privacy" className="w-fit hover:text-gray-700 dark:hover:text-white">
                  Quyền riêng tư
                </Link>
              </div>
            </div>
            <p className="text-[12px] text-center text-[#BCBCBC] dark:text-neutral-400">
              <a
                href="https://fatties.vercel.app"
                target="_blank"
                rel="noopener noreferrer"
                className="hover:text-gray-700 dark:hover:text-white"
              >
                Fatties Software
              </a>{" "}
              © 2025
            </p>
          </div>
        </div>
      </div>

      {/* Bottom bar for smaller screens - applying similar changes */}
    </>
  );
}
