import { Link } from "@inertiajs/react";
import { AddOutline, HelpCircleOutline } from "react-ionicons";
import { Button } from "antd";
import CustomColorButton from "./ui/CustomColorButton";

export default function RightSidebar() {
  // const { theme } = useTheme(); // Uncomment if you need theme
  // const iconColor = theme === 'dark' ? '#gray-300' : '#6B6B6B'; // Example dynamic color
  const iconColor = "#6B6B6B"; // Default static color for icons
  const iconSize = "20px"; // Default icon size

  return (
    <>
      {/* Right side bar */}
      <div className="w-full max-w-[775px] xl:w-[340px] mx-auto !pb-6 xl:p-6" id="right-sidebar">
        <div className="sticky top-[calc(69px+24px)]">
          <CustomColorButton
            bgColor={"#319527"}
            block
            className="text-base text-white font-semibold py-[19px] mb-1.5 hidden xl:flex"
          >
            <AddOutline color="#FFFFFF" height={iconSize} width={iconSize} cssClasses="-mr-1" />
            Tạo bài viết mới
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
            {/* User ranking list - internal links changed to Link */}
            <div className="flex flex-row items-center mt-2">
              <Link href="/Chocobaiii">
                <img
                  src="https://api.chuyenbienhoa.com/v1.0/users/Chocobaiii/avatar"
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link href="/Chocobaiii" className="ml-1.5 font-semibold flex-1 truncate text-left">
                Chocobaiii
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">173 điểm</span>
              <span className="text-green-500 font-bold">#1</span>
            </div>
            {/* ... Repeat for other users, changing <a> to <Link> ... */}
            <div className="flex flex-row items-center mt-2">
              <Link href="/DoanTruongCBH">
                <img
                  src="https://api.chuyenbienhoa.com/v1.0/users/DoanTruongCBH/avatar"
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link
                href="/DoanTruongCBH"
                className="ml-1.5 font-semibold flex-1 truncate text-left"
              >
                Đoàn trường THPT Chuyên Biên Hòa
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">170 điểm</span>
              <span className="text-green-500 font-bold">#2</span>
            </div>
            {/* ... (Continue for hoangphat, chi, ndhai, TuanAnhDaDen, kienthuctonghop, daomeomeoh) ... */}
            <div className="flex flex-row items-center mt-2">
              <Link href="/hoangphat">
                <img
                  src="https://api.chuyenbienhoa.com/v1.0/users/hoangphat/avatar"
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link href="/hoangphat" className="ml-1.5 font-semibold flex-1 truncate text-left">
                Hoàng Phát
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">104 điểm</span>
              <span className="text-green-500 font-bold">#3</span>
            </div>
            <div className="flex flex-row items-center mt-2">
              <Link href="/chi">
                <img
                  src="https://api.chuyenbienhoa.com/v1.0/users/chi/avatar"
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link href="/chi" className="ml-1.5 font-semibold flex-1 truncate text-left">
                nguyẽn kim chi
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">90 điểm</span>
              <span className="text-green-500 font-bold">#4</span>
            </div>
            <div className="flex flex-row items-center mt-2">
              <Link href="/ndhai">
                <img
                  src="https://api.chuyenbienhoa.com/v1.0/users/ndhai/avatar"
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link href="/ndhai" className="ml-1.5 font-semibold flex-1 truncate text-left">
                Nguyễn Đặng Hải
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">89 điểm</span>
              <span className="text-green-500 font-bold">#5</span>
            </div>
            <div className="flex flex-row items-center mt-2">
              <Link href="/TuanAnhDaDen">
                <img
                  src="/assets/images/placeholder-user.jpg" // Assuming local asset
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link href="/TuanAnhDaDen" className="ml-1.5 font-semibold flex-1 truncate text-left">
                quên rồi
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">78 điểm</span>
              <span className="text-green-500 font-bold">#6</span>
            </div>
            <div className="flex flex-row items-center mt-2">
              <Link href="/kienthuctonghop">
                <img
                  src="https://api.chuyenbienhoa.com/v1.0/users/kienthuctonghop/avatar"
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link
                href="/kienthuctonghop"
                className="ml-1.5 font-semibold flex-1 truncate text-left"
              >
                Kiến thức tổng hợp
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">62 điểm</span>
              <span className="text-green-500 font-bold">#7</span>
            </div>
            <div className="flex flex-row items-center mt-2">
              <Link href="/daomeomeoh">
                <img
                  src="/assets/images/placeholder-user.jpg" // Assuming local asset
                  className="w-8 h-8 bg-gray-300 rounded-full border"
                  alt="User avatar"
                />
              </Link>
              <Link href="/daomeomeoh" className="ml-1.5 font-semibold flex-1 truncate text-left">
                Phạm Xuân Đào
              </Link>
              <span className="mr-1.5 text-[#C1C1C1]">61 điểm</span>
              <span className="text-green-500 font-bold">#8</span>
            </div>
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
