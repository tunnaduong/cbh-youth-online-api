import HomeLayout from "@/Layouts/HomeLayout";
import { Head, Link } from "@inertiajs/react";
import React from "react";
import {
  ArrowDownOutline,
  ArrowUpOutline,
  Bookmark,
  ChatboxOutline,
  EyeOutline,
} from "react-ionicons";
import { ReactPhotoCollage } from "react-photo-collage";
import { usePage } from "@inertiajs/react";
import { CommentInput } from "@/Components/CommentInput";

export default function Show({ post }) {
  const { auth } = usePage().props;

  console.log(post);

  // hàm tạo layout + height tự động
  function getCollageSetting(photos) {
    const count = photos.length;

    if (count === 1) {
      return {
        width: "100%",
        height: ["100%"],
        layout: [1],
      };
    }

    if (count === 2) {
      return {
        width: "100%",
        height: ["100%"],
        layout: [2],
      };
    }

    if (count === 3) {
      return {
        width: "100%",
        height: ["100%"],
        layout: [3],
      };
    }

    // mặc định cho 4 ảnh trở lên
    return {
      width: "100%",
      height: ["275px", "170px"],
      layout: [2, 3], // ví dụ 2 ảnh hàng trên, 3 ảnh hàng dưới
    };
  }

  const setting = {
    ...getCollageSetting(post.image_urls),
    photos: post.image_urls.map((url) => ({ source: url })),
    showNumOfRemainingPhotos: true,
  };

  return (
    <HomeLayout activeNav="home" activeBar={null}>
      <Head title={post.title} />
      <div className="px-1 xl:min-h-screen pt-4">
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full">
          <div className="post-container-post post-container mb-4 shadow-lg rounded-xl !p-6 bg-white flex flex-col-reverse md:flex-row">
            <div className="min-w-[84px]">
              <div className="sticky-reaction-bar items-center md:!mt-1 mt-3 gap-x-3 flex md:!flex-col flex-row md:ml-[-20px] text-[13px] font-semibold text-gray-400">
                <ArrowUpOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <span className="select-none text-lg vote-count">{post.votes.length}</span>
                <ArrowDownOutline
                  height="26px"
                  width="26px"
                  color={"#9ca3af"}
                  className="cursor-pointer"
                />
                <div className="save-post-button bg-[#EAEAEA] dark:bg-neutral-500 cursor-pointer rounded-lg w-[33.6px] h-[33.6px] md:mt-3 flex items-center justify-center">
                  <Bookmark height="20px" width="20px" color={"#9ca3af"} />
                </div>
                <div className="flex-1"></div>
                <div className="flex-1 flex md:hidden flex-row-reverse items-center text-gray-500">
                  <span>{post.view_count}</span>
                  <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
                  <span className="flex flex-row-reverse items-center">
                    <span>{post.reply_count}+</span>
                    <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                  </span>
                </div>
              </div>
            </div>
            <div className="flex-1 overflow-hidden break-words">
              <h1 className="text-xl font-semibold mb-1">{post.title}</h1>
              <div className="text-base max-w-[600px] overflow-wrap prose">
                <p dangerouslySetInnerHTML={{ __html: post.content }} />
              </div>
              {post.image_urls.length != 0 && (
                <div className="square-wrapper mt-3 rounded overflow-hidden">
                  <ReactPhotoCollage {...setting} />
                </div>
              )}
              <hr className="!my-5 border-t-2" />
              <div className="flex-row flex-wrap flex text-[13px] items-center">
                <Link
                  href={route("profile.show", {
                    username: post.author.username,
                  })}
                >
                  <span className="relative flex shrink-0 overflow-hidden rounded-full w-8 h-8">
                    <img
                      className="border rounded-full aspect-square h-full w-full"
                      alt={post.author.username + " avatar"}
                      src={`https://api.chuyenbienhoa.com/v1.0/users/${post.author.username}/avatar`}
                    />
                  </span>
                </Link>
                <span className="text-gray-500 hidden md:block ml-2">Đăng bởi</span>
                <Link
                  className="flex flex-row items-center ml-2 md:ml-1 text-[#319527] hover:text-[#319527] font-bold hover:underline"
                  href={route("profile.show", {
                    username: post.author.username,
                  })}
                >
                  {post.author.profile_name}
                  {post.author.verified && (
                    <svg
                      stroke="currentColor"
                      fill="currentColor"
                      strokeWidth={0}
                      viewBox="0 0 20 20"
                      aria-hidden="true"
                      className="text-base leading-5 ml-0.5"
                      height="1em"
                      width="1em"
                      xmlns="http://www.w3.org/2000/svg"
                    >
                      <path
                        fillRule="evenodd"
                        d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                        clipRule="evenodd"
                      />
                    </svg>
                  )}
                </Link>
                <span className="mb-2 ml-0.5 text-sm text-gray-500">.</span>
                <span className="ml-0.5 text-gray-500">{post.created_at}</span>
                <div className="flex-1 flex-row-reverse items-center text-gray-500 hidden md:flex">
                  <span>{post.view_count}</span>
                  <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
                  <span className="flex flex-row-reverse items-center">
                    <span>{post.reply_count}+</span>
                    <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full mb-4">
          <div className="shadow !mb-4 long-shadow h-min rounded-lg bg-white post-comment-container">
            <div className="flex flex-col space-y-1.5 p-6 text-xl -mb-4 font-semibold max-w-sm overflow-hidden whitespace-nowrap overflow-ellipsis">
              Bình luận
            </div>
            <div className="p-6 pt-2">
              {!auth?.user ? (
                <div className="text-base !mb-8">
                  <Link className="text-green-600 hover:text-green-600" href="/login">
                    Đăng nhập
                  </Link>{" "}
                  để bình luận và tham gia thảo luận cùng cộng đồng.
                </div>
              ) : (
                <CommentInput
                  userAvatar={`https://api.chuyenbienhoa.com/v1.0/users/${auth.user.username}/avatar`}
                />
              )}
              {/* <Comment comment={post.comments} /> */}
            </div>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
