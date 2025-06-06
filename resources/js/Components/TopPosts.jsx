import RankBadge from "./RankBadge";
import { Link } from "@inertiajs/react";
import { timeAgoInVietnamese } from "@/Utils/dateFormat";

export default function TopPosts({ latestPosts }) {
    return (
        <div className="border dark:!border-[#585857] rounded long-shadow bg-white dark:!bg-[var(--main-white)]">
            <div className="flex flex-wrap items-stretch">
                <Link
                    href="?sort=latest"
                    className="px-4 text-sm flex items-center hover:bg-gray-50 tab-button dark:hover:bg-neutral-500 tab-button-active"
                >
                    <span className="py-2">Bài mới</span>
                </Link>
                <Link
                    href="?sort=most_viewed"
                    className="hidden sm:flex px-4 text-sm items-center bor-left hover:bg-gray-50 tab-button dark:border-[#585857] dark:hover:bg-neutral-500"
                >
                    <span className="py-2">Chủ đề xem nhiều</span>
                </Link>
                <Link
                    href="?sort=most_engaged"
                    className="px-4 text-sm hidden sm:flex items-center bor-right bor-left hover:bg-gray-50 tab-button dark:border-[#585857] dark:hover:bg-neutral-500"
                >
                    <span className="py-2">Tương tác nhiều</span>
                </Link>
                <div>
                    <button
                        className="h-9 w-9 border-l items-center justify-center tab-button bor-right flex sm:hidden hover:bg-gray-50 dark:border-neutral-500 dark:hover:bg-neutral-500"
                        id="dropdownMenu"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <ion-icon name="menu-outline" className="text-xl" />
                    </button>
                    <ul
                        className="dropdown-menu"
                        aria-labelledby="dropdownMenu"
                    >
                        <li>
                            <Link
                                className="dropdown-item"
                                href="?sort=most_viewed"
                            >
                                Chủ đề xem nhiều
                            </Link>
                        </li>
                        <li>
                            <Link
                                className="dropdown-item"
                                href="?sort=most_engaged"
                            >
                                Tương tác nhiều
                            </Link>
                        </li>
                    </ul>
                </div>
                <div className="ml-auto flex">
                    <button
                        className="h-9 w-9 border-l dark:border-[#585857] flex items-center justify-center tab-button hover:bg-gray-50 dark:hover:bg-neutral-500"
                        onClick={() => window.location.reload()}
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
                            className="lucide lucide-refresh-cw-icon lucide-refresh-cw h-4 w-4"
                        >
                            <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" />
                            <path d="M21 3v5h-5" />
                            <path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" />
                            <path d="M8 16H3v5" />
                        </svg>
                    </button>
                </div>
            </div>
            <div>
                {latestPosts.map((post, index) => (
                    <div
                        key={`post-${post.id}`}
                        className="bor-bottom dark:!border-b-[#585857] hover:bg-gray-50 flex py-1 px-2 dark:hover:bg-neutral-600"
                    >
                        <RankBadge index={index} />
                        <div className="flex items-center flex-1 max-w-[90%] overflow-hidden">
                            <Link
                                href={route("posts.show", {
                                    username: post.user.username,
                                    id: post.id,
                                })}
                                className="truncate block w-full text-[12.7px] text-[#319528] hover:underline"
                            >
                                {post.title}
                            </Link>
                        </div>
                        <div className="sm:flex items-center justify-end hidden text-right text-gray-500 text-[11px] whitespace-nowrap w-[100px] max-w-[100px]">
                            {timeAgoInVietnamese(post.created_at)}
                        </div>
                        <div className="sm:flex items-center pl-2 hidden text-right text-[11px] whitespace-nowrap w-[150px] max-w-[150px]">
                            <div className="flex items-center justify-end">
                                <Link
                                    href={route("profile.show", {
                                        username: post.user.username,
                                    })}
                                    className="text-[#319528] hover:underline truncate inline-block max-w-[150px]"
                                >
                                    {post.user.profile.profile_name}
                                </Link>
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
