import { useState } from "react";
import { Button } from "antd";
import {
  ArrowUpOutline,
  ArrowDownOutline,
  Bookmark,
  EyeOutline,
  ChatboxOutline,
  Play,
} from "react-ionicons";
import { Link } from "@inertiajs/react";
import AudioPlayer from "./AudioPlayer";

export default function RecordingItem({ recording }) {
  const [showFullContent, setShowFullContent] = useState(false);
  const maxLength = 300;

  const toggleShowFullContent = (e) => {
    e.preventDefault();
    setShowFullContent(!showFullContent);
  };

  const truncateHtml = (html, maxLength) => {
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = html;
    const textContent = tempDiv.textContent || tempDiv.innerText || "";

    if (textContent.length <= maxLength) {
      return html;
    }

    const truncatedText = textContent.substring(0, maxLength);
    const lastSpaceIndex = truncatedText.lastIndexOf(" ");
    const finalText =
      lastSpaceIndex > 0 ? truncatedText.substring(0, lastSpaceIndex) : truncatedText;

    tempDiv.textContent = finalText + "...";
    return tempDiv.innerHTML;
  };

  const getContentWithReadMore = () => {
    const textContent = recording.content_html?.replace(/<[^>]*>/g, "");
    const needsTruncation = textContent?.length > maxLength;

    if (!needsTruncation) {
      return recording.content_html;
    }

    let content;
    if (showFullContent) {
      content = recording.content_html;
    } else {
      content = truncateHtml(recording.content_html, maxLength);
    }

    const readMoreLink = showFullContent
      ? ' <span class="text-[var(--tw-prose-body)] dark:text-[rgb(209_213_219)] hover:underline text-base font-medium read-more-link cursor-pointer">Thu gọn</span>'
      : ' <span class="text-[var(--tw-prose-body)] dark:text-[rgb(209_213_219)] hover:underline text-base font-medium read-more-link cursor-pointer">Xem thêm</span>';

    return content + readMoreLink;
  };

  return (
    <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full">
      <div className="post-container-post post-container mb-4 shadow-lg rounded-xl !p-6 bg-white flex flex-col-reverse md:flex-row">
        <div className="min-w-[72px]">
          <div className="sticky-reaction-bar items-center md:!mt-1 mt-3 gap-x-3 flex md:!flex-col flex-row md:ml-[-20px] text-[13px] font-semibold text-gray-400">
            <Button size="small" className="w-8 px-2 text-gray-400 rounded-full border-0">
              <ArrowUpOutline height="26px" width="26px" color="currentColor" />
            </Button>
            <span className="select-none text-lg vote-count">
              {recording.votes?.reduce((acc, vote) => acc + vote.vote_value, 0) ||
                recording.votes_sum_vote_value ||
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
              <span>{recording.view_count || 0}</span>
              <EyeOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
              <span className="flex flex-row-reverse items-center">
                <span>0</span>
                <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
              </span>
            </div>
          </div>
        </div>
        <div className="flex-1 overflow-hidden break-words">
          <Link
            href={route("posts.show", {
              id: recording.id,
              username: recording.author.username,
            })}
          >
            <h1 className="text-xl font-semibold mb-1">{recording.title}</h1>
          </Link>
          <div
            className="text-base max-w-[600px] overflow-wrap prose mt-[0.75em]"
            dangerouslySetInnerHTML={{
              __html: getContentWithReadMore(),
            }}
            onClick={toggleShowFullContent}
          />

          {recording.cdn_audio_id && (
            <AudioPlayer
              src={`https://api.chuyenbienhoa.com/storage/${recording.cdn_audio.file_path}`}
              title={recording.title}
              artist={recording.author.profile_name || recording.author.profile.profile_name}
              thumbnail={`https://api.chuyenbienhoa.com/storage/${recording.cdn_preview.file_path}`}
              className={"mt-4"}
            />
          )}

          <hr className="!my-5 border-t-2" />
          <div className="flex-row flex-wrap flex text-[13px] items-center">
            <Link
              href={route("profile.show", {
                username: recording.author.username,
              })}
            >
              <span className="relative flex shrink-0 overflow-hidden rounded-full w-8 h-8">
                <img
                  className="border rounded-full aspect-square h-full w-full"
                  alt={recording.author.username + " avatar"}
                  src={`https://api.chuyenbienhoa.com/v1.0/users/${recording.author.username}/avatar`}
                />
              </span>
            </Link>
            <span className="text-gray-500 hidden md:block ml-2">Đăng bởi</span>
            <Link
              className="flex flex-row items-center ml-2 md:ml-1 text-[#319527] hover:text-[#319527] font-bold hover:underline"
              href={route("profile.show", {
                username: recording.author.username,
              })}
            >
              {recording.author.profile_name || recording.author.profile.profile_name}
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
