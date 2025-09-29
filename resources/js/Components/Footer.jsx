import React from "react";
import { LogoFacebook, LogoGithub } from "react-ionicons";
import { Link } from "@inertiajs/react";

export default function Footer() {
  return (
    <footer className="footer text-[#6B6B6B] dark:!text-white bg-white dark:!bg-[var(--main-white)] relative z-30 mt-4">
      <div className="bg-[#319527] shadow-md">
        {/* Menu */}
        <div className="container">
          <ul className="flex justify-start items-center py-4 text-white text-[14px] gap-6">
            <li className="hidden md:block">
              <Link href="/">Trang chủ</Link>
            </li>
            <li className="hidden md:block">
              <Link href="/help">Trợ giúp</Link>
            </li>
            <li>
              <Link href={route("policy.terms")}>Điều khoản &amp; Quy định</Link>
            </li>
            <li>
              <Link href={route("policy.privacy")}>Chính sách quyền riêng tư</Link>
            </li>
          </ul>
        </div>
      </div>
      <div className="container pt-4">
        <div>
          <div
            className="w-[50%] absolute h-full mb-4 top-0 right-0 -z-10 footer-bg"
            style={{
              backgroundImage: 'url("/images/footer.jpg")',
              backgroundSize: "cover",
              backgroundPosition: "center",
            }}
          ></div>
          <div className="fade-to-left" style={{ width: "50%" }} />
        </div>
        <div className="flex flex-wrap -mx-4">
          <div className="w-full md:w-1/4 px-4 mb-4 md:mb-0">
            <img src="/images/logo.png" alt="Logo" className="w-[100px] mb-3" />
            <h2 className="font-bold">Diễn đàn học sinh Chuyên Biên Hòa</h2>
            <div className="flex items-center gap-2 mt-3 text-[20px]">
              <a
                href="https://facebook.com/cbhyouthonline"
                target="_blank"
                className="rounded-full h-[35px] w-[35px] flex justify-center items-center bg-[#3b5998] text-white"
              >
                <LogoFacebook color={"white"} />
              </a>
              <a
                href="https://github.com/tunnaduong/cbh-youth-online-api"
                target="_blank"
                className="rounded-full h-[35px] w-[35px] flex justify-center items-center bg-black text-white"
              >
                <LogoGithub color={"white"} />
              </a>
            </div>
            <p className="text-[13px] mt-5">
              Trang web hoạt động phi lợi nhuận
              <br />
              <em>(không thuộc quản lý của nhà trường)</em>
            </p>
          </div>
          <div className="w-1/2 md:w-1/4 px-4 mb-4 md:mb-0">
            <h3 className="font-bold text-[16px]">Chuyên mục nổi bật</h3>
            <ul className="list-none mt-3 flex flex-col gap-2">
              <li>
                <Link href="/forum/hoc-tap" className="hover:text-[#319527]">
                  Góc học tập
                </Link>
              </li>
              <li>
                <Link
                  href="/forum/hoat-dong-ngoai-khoa/cau-lac-bo"
                  className="hover:text-[#319527]"
                >
                  Câu lạc bộ
                </Link>
              </li>
              <li>
                <Link href="/forum/hoat-dong-ngoai-khoa" className="hover:text-[#319527]">
                  Hoạt động
                </Link>
              </li>
              <li>
                <Link href="/forum/giai-tri-xa-hoi" className="hover:text-[#319527]">
                  Giải trí
                </Link>
              </li>
              <li>
                <Link href="/forum/hoc-tap/ebook-giao-trinh" className="hover:text-[#319527]">
                  Tài liệu ôn thi
                </Link>
              </li>
            </ul>
          </div>
          <div className="w-1/2 md:w-1/4 px-4 mb-4 md:mb-0">
            <h3 className="font-bold text-[16px]">Chính sách</h3>
            <ul className="list-none mt-3 flex flex-col gap-2">
              <li>
                <Link href={route("policy.forum-rules")} className="hover:text-[#319527]">
                  Nội quy diễn đàn
                </Link>
              </li>
              <li>
                <Link href={route("policy.privacy")} className="hover:text-[#319527]">
                  Chính sách bảo mật
                </Link>
              </li>
              <li>
                <Link href={route("policy.terms")} className="hover:text-[#319527]">
                  Điều khoản sử dụng
                </Link>
              </li>
            </ul>
          </div>
          <div className="w-full md:w-1/4 px-4">
            <h3 className="font-bold text-[16px]">Liên hệ &amp; Hỗ trợ</h3>
            <ul className="list-none mt-3 flex flex-col gap-2">
              <li>
                Email:{" "}
                <a href="mailto:hotro@chuyenbienhoa.com" className="hover:text-[#319527]">
                  hotro@chuyenbienhoa.com
                </a>
              </li>
              <li>
                Hotline:{" "}
                <a href="tel:+84365520031" className="hover:text-[#319527]">
                  (+84) 3655 200 31
                </a>
              </li>
              <li>
                Fanpage:{" "}
                <a
                  href="https://facebook.com/cbhyouthonline"
                  target="_blank"
                  className="hover:text-[#319527]"
                >
                  @CBHYouthOnline
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div className="flex flex-wrap -mx-4 text-[12px] py-4">
          <div className="w-full px-4 text-center">
            <p>
              © 2020-{new Date().getFullYear()}{" "}
              <a href="https://fatties.vercel.app">
                Công ty Cổ phần Giải pháp Giáo dục Fatties Software
              </a>{" "}
              - Được phát triển bởi học sinh, dành cho học sinh.
            </p>
          </div>
        </div>
      </div>
    </footer>
  );
}
