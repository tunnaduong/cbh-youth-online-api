import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import React from "react";
import {
  ArrowDownOutline,
  ArrowUpOutline,
  Bookmark,
  ChatboxOutline,
  EyeOutline,
} from "react-ionicons";

export default function Show() {
  return (
    <HomeLayout activeNav="home">
      <Head title="Diễn đàn học sinh Chuyên Biên Hòa" />
      <div className="px-1 xl:min-h-screen pt-4">
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full">
          <div className="post-container-post post-container mb-4 shadow-lg rounded-xl p-6! bg-white flex flex-row">
            <div className="min-w-[84px] hidden md:block">
              <div className="sticky-reaction-bar items-center mt-1 flex flex-col ml-[-20px] text-[13px] font-semibold text-gray-400">
                <ArrowUpOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <span className="select-none text-lg vote-count">6</span>
                <ArrowDownOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <div className="save-post-button bg-[#EAEAEA] dark:bg-neutral-500 cursor-pointer rounded-lg w-[33.6px] h-[33.6px] mt-3 flex items-center justify-center">
                  <Bookmark height="20px" width="20px" color={"#9ca3af"} />
                </div>
              </div>
            </div>
            <div className="flex-1 overflow-hidden break-words">
              <h1 className="text-xl font-semibold mb-1">我住在河南，越南。很高兴认识你！</h1>
              <div className="text-base max-w-[600px] overflow-wrap prose">
                <p>
                  你好👋。我叫羊松英。我是越南人。今年我二十二岁。我非常喜欢听音乐！我的家在河南，越南。很高兴认识你！
                </p>
              </div>
              <hr className="my-5! border-t-2" />
              <div className="flex-row flex-wrap flex text-[13px] items-center">
                <a href="/yangsongying">
                  <span className="relative flex shrink-0 overflow-hidden rounded-full w-8 h-8">
                    <img
                      className="border rounded-full aspect-square h-full w-full"
                      alt="yangsongying avatar"
                      src="https://api.chuyenbienhoa.com/v1.0/users/yangsongying/avatar"
                    />
                  </span>
                </a>
                <span className="text-gray-500 hidden md:block ml-2">Đăng bởi</span>
                <a
                  className="flex flex-row items-center ml-2 md:ml-1 text-[#319527] hover:text-[#319527] font-bold hover:underline"
                  href="/yangsongying"
                >
                  羊松英
                </a>
                <span className="mb-2 ml-0.5 text-sm text-gray-500">.</span>
                <span className="ml-0.5 text-gray-500">4 tháng trước</span>
                <div className="flex-1 flex-row-reverse items-center text-gray-500 hidden sm:flex">
                  <span>424</span>
                  <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
                  <span className="flex flex-row-reverse items-center">
                    <span>40+</span>
                    <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                  </span>
                </div>
              </div>
              <div className="min-w-[84px] mt-3 flex md:hidden items-center gap-x-3 flex-row text-[13px] font-semibold text-gray-400">
                <ArrowUpOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <span className="select-none text-lg vote-count ">6</span>
                <ArrowDownOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <div className="save-post-button bg-[#EAEAEA] cursor-pointer rounded-lg w-[33.6px] h-[33.6px] flex items-center justify-center">
                  <Bookmark height="20px" width="20px" color={"#9ca3af"} />
                </div>
                <div className="flex flex-1 flex-row-reverse items-center text-gray-500 sm:hidden">
                  <span>424</span>
                  <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
                  <span className="flex flex-row-reverse items-center">
                    <span>40+</span>
                    <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full mb-4">
          <div className="shadow mb-4! long-shadow h-min rounded-lg bg-white post-comment-container">
            <div className="flex flex-col space-y-1.5 p-6 text-xl -mb-4 font-semibold max-w-sm overflow-hidden whitespace-nowrap text-ellipsis">
              Bình luận
            </div>
            <div className="p-6 pt-2">
              <div className="text-base mb-8!">
                <a className="text-green-600 hover:text-green-600" href="/login">
                  Đăng nhập
                </a>{" "}
                để bình luận và tham gia thảo luận cùng cộng đồng.
              </div>
              <div className="gap-y-4 flex flex-col">
                <div className="flex space-x-4">
                  <a href="/Chocobaiii">
                    <span className="relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full">
                      <img
                        src="https://api.chuyenbienhoa.com/v1.0/users/Chocobaiii/avatar"
                        className="flex h-full w-full items-center justify-center rounded-full border"
                      />
                    </span>
                  </a>
                  <div className="flex-1 relative">
                    <div className="flex items-center justify-between gap-2">
                      <a href="/Chocobaiii">
                        <h4 className="text-sm font-semibold flex items-center">
                          <span className="dont-break-out">Chocobaiii</span>
                        </h4>
                      </a>
                      <span className="text-xs text-gray-500 shrink-0">3 tháng trước</span>
                    </div>
                    <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">
                      Hiuhiu chết r em quên chỉnh sửa bài nên trông post buồn cười quá^^. Huhu h sao
                      để sửa ạ 😭
                    </p>
                    <div className="mt-2 flex items-center space-x-2 text-gray-400">
                      <ArrowUpOutline
                        color={"#9ca3af"}
                        height="19px"
                        width="19px"
                        className="cursor-pointer"
                      />
                      <span className="vote-count select-none text-sm font-semibold ">2</span>
                      <ArrowDownOutline
                        color={"#9ca3af"}
                        height="19px"
                        width="19px"
                        className="cursor-pointer"
                      />
                      <span>·</span>
                      <span className="reply-comment cursor-pointer text-sm font-semibold">
                        Trả lời
                      </span>
                    </div>
                    <form action="" method="POST" className="reply-box hidden mt-2 space-y-4">
                      <textarea
                        className="flex dark:border-neutral-500! min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        name="comment"
                        required=""
                        placeholder="Viết phản hồi của bạn..."
                        spellCheck="false"
                        data-ms-editor="true"
                        defaultValue={""}
                      />
                      <input type="hidden" name="replyingTo" defaultValue={292} />
                      <div className="flex justify-end">
                        <button
                          className="inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 text-primary-foreground shadow h-9 px-4 py-2 bg-green-600 hover:bg-green-700 text-white"
                          type="submit"
                        >
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width={24}
                            height={24}
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="lucide lucide-send mr-2 h-4 w-4"
                          >
                            <path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"></path>
                            <path d="m21.854 2.147-10.94 10.939" />
                          </svg>
                          Gửi phản hồi
                        </button>
                      </div>
                    </form>
                    <div className="mt-4 space-y-4 reply-container dark:border-neutral-500 absolute left-0 right-0 z-10">
                      <div className="flex space-x-4">
                        <a href="/Tunna">
                          <span className="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-full">
                            <img
                              src="https://api.chuyenbienhoa.com/v1.0/users/Tunna/avatar"
                              className="flex h-full w-full items-center justify-center rounded-full border"
                            />
                          </span>
                        </a>
                        <div className="flex-1">
                          <div className="flex items-center justify-between gap-2">
                            <a href="/Tunna">
                              <h4 className="text-sm font-semibold flex items-center">
                                <span className="dont-break-out">Tunna Duong</span>
                              </h4>
                            </a>
                            <span className="text-xs text-gray-500 shrink-0">
                              3 tháng trước
                            </span>
                          </div>
                          <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">
                            :))) không có cách nào đâu e. Đợi a code tính năng đó đã :v
                          </p>
                          <div className="mt-2 flex items-center space-x-2 text-gray-400">
                            <ArrowUpOutline
                              color={"#9ca3af"}
                              height="19px"
                              width="19px"
                              className="cursor-pointer"
                            />
                            <span className="vote-count select-none text-sm font-semibold ">2</span>
                            <ArrowDownOutline
                              color={"#9ca3af"}
                              height="19px"
                              width="19px"
                              className="cursor-pointer"
                            />
                            <span>·</span>
                            <span className="reply-comment cursor-pointer text-sm font-semibold">
                              Trả lời
                            </span>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
