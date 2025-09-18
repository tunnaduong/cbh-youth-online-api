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
        </div>
      </div>
    </div>
  );
}
