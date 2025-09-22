import { Link } from "@inertiajs/react";
import {
  ArrowUpOutline,
  ArrowDownOutline,
  Bookmark,
  EyeOutline,
  ChatboxOutline,
} from "react-ionicons";
import { generatePostSlug } from "@/Utils/slugify";
import { ReactPhotoCollage } from "react-photo-collage";
import VerifiedBadge from "@/Components/ui/VerifiedBadge";
import getCollageSetting from "@/Utils/getCollageSetting";
import { useState } from "react";
import { Button } from "antd";

export default function PostItem({ post, single = false }) {
  const [showFullContent, setShowFullContent] = useState(false);
  const maxLength = 300; // Số ký tự tối đa trước khi truncate

  const toggleShowFullContent = (e) => {
    e.preventDefault();
    setShowFullContent(!showFullContent);
  };

  const truncateHtml = (html, maxLength) => {
    // Tạo một temporary div để parse HTML
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = html;

    // Lấy text content (không có HTML tags)
    const textContent = tempDiv.textContent || tempDiv.innerText || "";

    if (textContent.length <= maxLength) {
      return html;
    }

    // Truncate text và thêm dấu ...
    const truncatedText = textContent.substring(0, maxLength);
    const lastSpaceIndex = truncatedText.lastIndexOf(" ");
    const finalText =
      lastSpaceIndex > 0 ? truncatedText.substring(0, lastSpaceIndex) : truncatedText;

    // Trả về HTML đã được truncate (giữ lại formatting cơ bản)
    tempDiv.textContent = finalText + "...";
    return tempDiv.innerHTML;
  };

  const getContentWithReadMore = () => {
    const textContent = post.content?.replace(/<[^>]*>/g, ""); // Remove HTML tags để đếm text
    const needsTruncation = textContent?.length > maxLength;

    if (!needsTruncation) {
      return post.content;
    }

    let content;
    if (showFullContent) {
      content = post.content;
    } else {
      content = truncateHtml(post.content, maxLength);
    }

    const readMoreLink = showFullContent
      ? ' <span class="text-[var(--tw-prose-body)] dark:text-[rgb(209_213_219)] hover:underline text-base font-medium read-more-link cursor-pointer">Thu gọn</span>'
      : ' <span class="text-[var(--tw-prose-body)] dark:text-[rgb(209_213_219)] hover:underline text-base font-medium read-more-link cursor-pointer">Xem thêm</span>';

    return content + readMoreLink;
  };

  const setting = {
    ...getCollageSetting(post.image_urls),
    photos: post.image_urls.map((url) => ({ source: url })),
    showNumOfRemainingPhotos: true,
  };

  return (
    <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full" key={post.id}>
      <div className="post-container-post post-container mb-4 shadow-lg rounded-xl !p-6 bg-white flex flex-col-reverse md:flex-row">
        <div className="min-w-[72px]">
          <div className="sticky-reaction-bar items-center md:!mt-1 mt-3 gap-x-3 flex md:!flex-col flex-row md:ml-[-20px] text-[13px] font-semibold text-gray-400">
            <Button size="small" className="w-8 px-2 text-gray-400 rounded-full border-0">
              <ArrowUpOutline height="26px" width="26px" color="currentColor" />
            </Button>
            <span className="select-none text-lg vote-count">
              {post.votes?.reduce((acc, vote) => acc + vote.vote_value, 0) ||
                post.votes_sum_vote_value ||
                0}
            </span>
            <Button
              size="small"
              className="w-8 px-2 text-gray-400 hover:!text-red-500 hover:!bg-red-50 dark:hover:!bg-[rgba(69,10,10,0.2)] rounded-full border-0"
            >
              <ArrowDownOutline height="26px" width="26px" color="currentColor" />
            </Button>
            <Button
              size="small"
              className="border-0 bg-[#EAEAEA] dark:bg-neutral-500 rounded-lg w-[33.6px] h-[33.6px] md:mt-3 flex items-center justify-center"
            >
              <Bookmark height="20px" width="20px" color={"#9ca3af"} />
            </Button>
            <div className="flex-1"></div>
            <div className="flex-1 flex md:hidden flex-row-reverse items-center text-gray-500">
              <span>{post.view_count}</span>
              <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
              <span className="flex flex-row-reverse items-center">
                <span>{post.reply_count}</span>
                <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
              </span>
            </div>
          </div>
        </div>
        <div className="flex-1 overflow-hidden break-words">
          {single ? (
            <h1 className="text-xl font-semibold mb-1">{post.title}</h1>
          ) : (
            <Link
              href={route("posts.show", {
                id: generatePostSlug(post.id, post.title),
                username: post.author.username,
              })}
            >
              <h1 className="text-xl font-semibold mb-1">{post.title}</h1>
            </Link>
          )}
          <div
            className="text-base max-w-[600px] overflow-wrap prose mt-[0.75em]"
            dangerouslySetInnerHTML={{ __html: single ? post.content : getContentWithReadMore() }}
            onClick={(e) => {
              if (!single && e.target.classList.contains("read-more-link")) {
                toggleShowFullContent(e);
              }
            }}
          />
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
              {post.author.profile_name || post.author.profile.profile_name}
              {(post.author.verified || post.author?.profile?.verified) && <VerifiedBadge />}
            </Link>
            <span className="mb-2 ml-0.5 text-sm text-gray-500">.</span>
            <span className="ml-0.5 text-gray-500">{post.created_at_human || post.created_at}</span>
            <div className="flex-1 flex-row-reverse items-center text-gray-500 hidden md:flex">
              <span>{post.view_count || post.views_count}</span>
              <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
              {!single ? (
                <Link
                  href={route("posts.show", {
                    id: generatePostSlug(post.id, post.title),
                    username: post.author.username,
                  })}
                  className="flex flex-row-reverse items-center"
                >
                  <span>{post.reply_count || post.comments_count}</span>
                  <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                </Link>
              ) : (
                <span className="flex flex-row-reverse items-center">
                  <span>{post.reply_count || post.comments_count}</span>
                  <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                </span>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
