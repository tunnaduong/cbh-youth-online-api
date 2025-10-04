import { useState } from "react";
import { Link, usePage } from "@inertiajs/react";
import { IoCloseCircle } from "react-icons/io5";

export default function BottomCTA() {
  const [isVisible, setIsVisible] = useState(true);
  const { props, url } = usePage();
  const user = props.auth?.user;

  const isOnAuthPage =
    url.startsWith("/login") ||
    url.startsWith("/register") ||
    url.startsWith("/forgot-password") ||
    url.startsWith("/reset-password");

  if (user || !isVisible || isOnAuthPage) {
    return null;
  }

  return (
    <div className="dark:!bg-neutral-700 dark:!text-[#f3f4f6] !p-6 !px-2.5 bg-white fixed bottom-0 z-50 w-full shadow-lg rounded-t-xl">
      <div className="container-cta relative">
        {/* Close button */}
        <button
          className="absolute -top-2 right-1 text-gray-500 dark:!text-[#bdbdbd] dark:hover:!text-[#cdcdcd] hover:text-gray-700"
          onClick={() => setIsVisible(false)}
        >
          <IoCloseCircle className="text-[30px]" />
        </button>
        {/* Content */}
        <div className="max-w-[775px] mx-auto flex items-center">
          <img
            src="/images/logo.png"
            alt="CYO Logo"
            className="w-28 logo-white h-28 hidden sm:block mr-4"
          />
          <div className="flex flex-col items-center justify-center text-center flex-1">
            <h2 className="text-2xl font-bold">Tham gia cộng đồng</h2>
            <p className="text-gray-500 mt-2">
              Chia sẻ ý kiến và kết nối với những người có cùng sở thích.
            </p>
            <div className="flex gap-2 sm:!gap-10">
              <Link
                href={`${route("login")}?continue=${encodeURIComponent(window.location.href)}`}
                className="mt-3 px-4 py-2 bg-gray-500 !text-white rounded-lg zoom-btn"
              >
                Đã có tài khoản?
              </Link>
              <Link
                href={`${route("register")}?continue=${encodeURIComponent(window.location.href)}`}
                className="mt-3 px-4 py-2 bg-[#319528] !text-white rounded-lg zoom-btn"
              >
                Tham gia ngay
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
