import { ArrowDownOutline, ArrowUpOutline } from "react-ionicons";
import { Link } from "@inertiajs/react";

export default function Comment({ comment }) {
  return (
    <div className="flex flex-col">
      <div className="flex space-x-4">
        <Link href={`/users/${comment.user.username}`}>
          <span className="relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full">
            <img
              src={comment.user.avatar}
              className="flex h-full w-full items-center justify-center rounded-full border"
            />
          </span>
        </Link>
        <div className="flex-1">
          <div className="flex items-center justify-between gap-2">
            <Link href={`/users/${comment.user.username}`}>
              <h4 className="text-sm font-semibold flex items-center">
                <span className="dont-break-out">{comment.user.username}</span>
              </h4>
            </Link>
            <span className="text-xs text-gray-500 flex-shrink-0">{comment.created_at}</span>
          </div>
          <p className="mt-1 text-sm text-gray-700 dark:text-gray-300">{comment.content}</p>
          <div className="mt-2 flex items-center space-x-2 text-gray-400">
            <ArrowUpOutline
              color={"#9ca3af"}
              height="19px"
              width="19px"
              className="cursor-pointer"
            />
            <span className="vote-count select-none text-sm font-semibold ">
              {comment.vote_count}
            </span>
            <ArrowDownOutline
              color={"#9ca3af"}
              height="19px"
              width="19px"
              className="cursor-pointer"
            />
            <span>·</span>
            <span className="reply-comment cursor-pointer text-sm font-semibold">Trả lời</span>
          </div>
          <form action="" method="POST" className="reply-box hidden mt-2 space-y-4">
            <textarea
              className="flex dark:!border-neutral-500 min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
              name="comment"
              required=""
              placeholder="Viết phản hồi của bạn..."
              spellCheck="false"
              data-ms-editor="true"
              defaultValue={""}
            />
            <input type="hidden" name="replyingTo" defaultValue={comment.id} />
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
        </div>
      </div>
      {/* 2 level cmt */}
      <div className="space-y-4 reply-container dark:border-neutral-500">
        <div className="flex space-x-4 mt-3">
          <Link href="/Tunna">
            <span className="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-full">
              <img
                src="https://api.chuyenbienhoa.com/v1.0/users/Tunna/avatar"
                className="flex h-full w-full items-center justify-center rounded-full border"
              />
            </span>
          </Link>
          <div className="flex-1">
            <div className="flex items-center justify-between gap-2">
              <Link href="/Tunna">
                <h4 className="text-sm font-semibold flex items-center">
                  <span className="dont-break-out">Tunna Duong</span>
                </h4>
              </Link>
              <span className="text-xs text-gray-500 flex-shrink-0">3 tháng trước</span>
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
              <span className="reply-comment cursor-pointer text-sm font-semibold">Trả lời</span>
            </div>
          </div>
        </div>
      </div>
      {/* 2 level cmt */}
      <div className="space-y-4 reply-container dark:border-neutral-500">
        <div className="flex space-x-4 mt-3">
          <Link href="/Tunna">
            <span className="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-full">
              <img
                src="https://api.chuyenbienhoa.com/v1.0/users/Tunna/avatar"
                className="flex h-full w-full items-center justify-center rounded-full border"
              />
            </span>
          </Link>
          <div className="flex-1">
            <div className="flex items-center justify-between gap-2">
              <Link href="/Tunna">
                <h4 className="text-sm font-semibold flex items-center">
                  <span className="dont-break-out">Tunna Duong</span>
                </h4>
              </Link>
              <span className="text-xs text-gray-500 flex-shrink-0">3 tháng trước</span>
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
              <span className="reply-comment cursor-pointer text-sm font-semibold">Trả lời</span>
            </div>
          </div>
        </div>
      </div>
      {/* 3 level cmt */}
      <div className="ml-6 space-y-4 reply-container dark:border-neutral-500">
        <div className="flex space-x-4 mt-3">
          <Link href="/Tunna">
            <span className="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-full">
              <img
                src="https://api.chuyenbienhoa.com/v1.0/users/Tunna/avatar"
                className="flex h-full w-full items-center justify-center rounded-full border"
              />
            </span>
          </Link>
          <div className="flex-1">
            <div className="flex items-center justify-between gap-2">
              <Link href="/Tunna">
                <h4 className="text-sm font-semibold flex items-center">
                  <span className="dont-break-out">Tunna Duong</span>
                </h4>
              </Link>
              <span className="text-xs text-gray-500 flex-shrink-0">3 tháng trước</span>
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
              <span className="reply-comment cursor-pointer text-sm font-semibold">Trả lời</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
