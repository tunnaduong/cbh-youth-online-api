import HelpCenterLayout from "@/Layouts/HelpCenterLayout";

export default function Index() {
  return (
    <HelpCenterLayout activeBar="help">
      <div className="container-main !mt-14 px-3">
        <h1 className="text-2xl font-bold">Chúng tôi có thể giúp gì cho bạn?</h1>

        <form action="/help/search" method="GET">
          <input
            type="text"
            name="query"
            className="w-full dark:bg-[var(--main-white)] focus:ring-0 focus:outline-none focus:!border-primary-500 p-3 mt-3 rounded-xl border border-[#ECECEC] text-base"
            placeholder="Tìm kiếm bài viết trợ giúp..."
          />
        </form>

        <h2 className="text-[20px] font-semibold mt-10 mb-3">Chủ đề phổ biến</h2>

        <div className="flex flex-wrap gap-y-3">
          <div className="w-full md:w-1/3 px-1.5">
            <a
              href="/help/1"
              className="flex flex-col justify-center bg-[#eaebec] dark:bg-[var(--main-white)] dark:hover:bg-neutral-700 rounded-lg p-3 hover:bg-[#e1e2e3]"
            >
              <div className="flex items-center justify-center">
                <img src="/images/help_account.png" width="100" height="100" alt="Tài khoản" />
              </div>
              <div className="mt-4">
                <h3 className="text-[15px] font-semibold mb-1">Cài đặt tài khoản</h3>
                <p className="text-[12px] text-gray-500">
                  Điều chỉnh cài đặt, quản lý thông báo, tìm hiểu về thay đổi tên và các nội dung
                  khác.
                </p>
              </div>
            </a>
          </div>

          <div className="w-full md:w-1/3 px-1.5">
            <a
              href="/help/2"
              className="flex flex-col justify-center bg-[#eaebec] dark:bg-[var(--main-white)] dark:hover:bg-neutral-700 rounded-lg p-3 hover:bg-[#e1e2e3]"
            >
              <div className="flex items-center justify-center">
                <img src="/images/help_login.png" width="100" height="100" alt="Đăng nhập" />
              </div>
              <div className="mt-4">
                <h3 className="text-[15px] font-semibold mb-1">Đăng nhập và mật khẩu</h3>
                <p className="text-[12px] text-gray-500">
                  Khắc phục sự cố khi đăng nhập và tìm hiểu cách thay đổi hoặc đặt lại mật khẩu.
                </p>
              </div>
            </a>
          </div>

          <div className="w-full md:w-1/3 px-1.5">
            <a
              href="/help/3"
              className="flex flex-col justify-center bg-[#eaebec] dark:bg-[var(--main-white)] dark:hover:bg-neutral-700 rounded-lg p-3 hover:bg-[#e1e2e3]"
            >
              <div className="flex items-center justify-center">
                <img src="/images/help_privacy.png" width="100" height="100" alt="Quyền riêng tư" />
              </div>
              <div className="mt-4">
                <h3 className="text-[15px] font-semibold mb-1">Quyền riêng tư và bảo mật</h3>
                <p className="text-[12px] text-gray-500">
                  Kiểm soát đối tượng có thể nhìn thấy nội dung bạn chia sẻ và gia tăng mức độ bảo
                  vệ tài khoản.
                </p>
              </div>
            </a>
          </div>
        </div>
      </div>
    </HelpCenterLayout>
  );
}
