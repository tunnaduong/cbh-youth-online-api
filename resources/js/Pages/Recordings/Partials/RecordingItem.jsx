import { Button } from "antd";
import {
  ArrowUpOutline,
  ArrowDownOutline,
  Bookmark,
  ChatboxOutline,
  PlayOutline,
} from "react-ionicons";
import { Link } from "@inertiajs/react";
import AudioPlayer from "./AudioPlayer";
import { generatePostSlug } from "@/Utils/slugify";
import Badges from "@/Components/ui/Badges";

export default function RecordingItem({ recording, single = false }) {
  return (
    <div className="px-1.5 md:px-0 md:max-w-[775px] mx-auto w-full">
      <div className="post-container-post post-container mb-4 bg-white flex flex-col-reverse md:flex-row rounded-xl shadow-lg p-6">
        <div className="min-w-[72px]">
          <div className="sticky-reaction-bar items-center md:!mt-1 mt-3 gap-x-3 flex md:!flex-col flex-row md:ml-[-20px] text-[13px] font-semibold text-gray-400">
            <Button size="small" className="w-8 px-2 text-gray-400 rounded-full border-0">
              <ArrowUpOutline height="26px" width="26px" color="currentColor" />
            </Button>
            <span className="select-none text-lg text-gray-400 font-semibold">
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
              <span>{recording.views_count ?? 0}</span>
              <PlayOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
              <span className="flex flex-row-reverse items-center">
                <span>{recording.views_count ?? 0}</span>
                <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
              </span>
            </div>
          </div>
        </div>
        <div className="flex-1 overflow-hidden break-words">
          {recording.cdn_audio_id && (
            <AudioPlayer
              src={`https://api.chuyenbienhoa.com/storage/${recording.cdn_audio.file_path}`}
              title={recording.title}
              thumbnail={
                recording.cdn_preview
                  ? `https://api.chuyenbienhoa.com/storage/${
                      recording.cdn_preview.file_path
                    }?v=${new Date().getTime()}`
                  : false
              }
              content={recording.content_html}
              id={recording.id}
            />
          )}

          <hr className="!my-5 border-t-2" />
          <div className="flex flex-row items-center justify-between text-[13px]">
            <div className="flex-row flex items-center">
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
                className="flex flex-row items-center ml-2 md:ml-1 text-[#319527] hover:text-[#319527] font-bold hover:underline inline-verified truncate"
                href={route("profile.show", {
                  username: recording.author.username,
                })}
              >
                <span className="inline-verified__text truncate">
                  {recording.author.profile_name || recording.author.profile.profile_name}
                </span>
                {recording.author.profile.verified && <Badges className="inline-verified__badge" />}
              </Link>
              <span className="mb-2 ml-0.5 text-sm text-gray-500">.</span>
              <span className="ml-0.5 text-gray-500 shrink-0">
                {recording.created_at_human || recording.created_at}
              </span>
            </div>
            <div className="flex-1 flex-row-reverse items-center text-gray-500 hidden md:flex">
              <span>{recording.views_count ?? 0}</span>
              <PlayOutline height="20px" width="20px" color={"#9ca3af"} className="ml-2 mr-1" />
              {!single ? (
                <Link
                  href={route("recordings.show", {
                    id: generatePostSlug(recording.id, recording.title),
                  })}
                  className="flex flex-row-reverse items-center"
                >
                  <span>{recording.views_count ?? 0}</span>
                  <ChatboxOutline height="20px" width="20px" color={"#9ca3af"} className="mr-1" />
                </Link>
              ) : (
                <span className="flex flex-row-reverse items-center">
                  <span>{recording.views_count ?? 0}</span>
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
