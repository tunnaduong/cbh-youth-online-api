import { Link } from "@inertiajs/react";
import { Button, Dropdown } from "antd";
import { generatePostSlug } from "@/Utils/slugify";
import VerifiedBadge from "@/Components/ui/Badges";
import { BsThreeDots } from "react-icons/bs";

export default function SavedPostItem({ post, onUnsave }) {
  const items = [
    {
      key: "1",
      label: "Bỏ lưu",
      onClick: () => onUnsave(post.id),
    },
  ];

  return (
    <div key={post.id} className="flex gap-4 p-3 bg-white dark:!bg-[#3c3c3c] rounded-lg shadow-sm">
      <Link
        href={route("posts.show", {
          id: generatePostSlug(post.id, post.title),
          username: post.anonymous ? "anonymous" : post.author.username,
        })}
        className="shrink-0"
      >
        <img
          src={
            post.image_urls[0]?.file_path
              ? "https://api.chuyenbienhoa.com/storage/" + post.image_urls[0].file_path
              : "https://api.chuyenbienhoa.com/v1.0/users/" + post.author.username + "/avatar"
          }
          alt={post.title}
          className="w-36 h-36 object-cover rounded-lg"
        />
      </Link>

      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <Link
            href={route("posts.show", {
              id: generatePostSlug(post.id, post.title),
              username: post.anonymous ? "anonymous" : post.author.username,
            })}
            className="text-lg font-medium hover:underline line-clamp-2"
          >
            {post.title}
          </Link>

          <Dropdown menu={{ items }} placement="bottomRight" trigger={["click"]}>
            <Button
              type="text"
              icon={<BsThreeDots size={20} />}
              className="flex items-center justify-center w-8 h-8 !p-0 hover:bg-gray-100 dark:hover:bg-gray-800"
            />
          </Dropdown>
        </div>

        <div className="text-sm">
          {post.anonymous ? (
            <span className="text-gray-600 dark:text-gray-400">Người dùng ẩn danh</span>
          ) : (
            <div className="flex items-center gap-2">
              <img
                src={`https://api.chuyenbienhoa.com/v1.0/users/${post.author.username}/avatar`}
                alt={post.author.username}
                className="w-7 h-7 rounded-full"
              />
              <Link
                href={route("profile.show", { username: post.author.username })}
                className="text-[#319527] hover:underline inline-verified truncate"
              >
                <span className="inline-verified__text truncate">
                  {post.author.profile_name || post.author.profile?.profile_name}
                </span>
                {(post.author.verified == "1" || post.author?.profile?.verified == "1") && (
                  <VerifiedBadge className="inline-verified__badge" />
                )}
              </Link>
            </div>
          )}
        </div>

        <div className="mt-3 text-sm text-gray-500">
          Đã lưu {post.saved_at_human || post.saved_at}
        </div>
      </div>
    </div>
  );
}
