import HomeLayout from "@/Layouts/HomeLayout";
import { Head, Link } from "@inertiajs/react";
import React from "react";

export default function Subforum({ category, subforum, topics }) {
  console.log(category, subforum, topics);
  return (
    <HomeLayout activeNav="home">
      <Head title={subforum.name} />
      <div className="px-2.5 min-h-screen py-6 flex justify-center">
        <div className="max-w-[775px] w-full">
          {/* Breadcrumb */}
          <nav aria-label="breadcrumb">
            <ol className="breadcrumb px-1.5">
              <li className="breadcrumb-item">
                <Link href="/" className=" flex items-center">
                  Diễn đàn
                </Link>
              </li>
              <li className="breadcrumb-item before:!text-neutral-400">
                <Link href={route("forum.category", { category: category.slug })}>
                  {category.name}
                </Link>
              </li>
              <li
                className="breadcrumb-item active dark:text-neutral-400 before:!text-neutral-400"
                aria-current="page"
              >
                {subforum.name}
              </li>
            </ol>
          </nav>
          {/* Forum Header */}
          <div className="w-full mb-6">
            <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2 p-4 relative z-10 overflow-hidden">
              <div>
                <div
                  className="w-[50%] absolute h-full mb-4 top-0 right-0 -z-10"
                  style={{
                    backgroundImage: `url("/images/${subforum.background_image}")`,
                    backgroundSize: "cover",
                    backgroundPosition: "center",
                  }}
                ></div>
                <div className="fade-to-left" />
              </div>
              <Link
                href={route("forum.subforum", { category: category.slug, subforum: subforum.slug })}
                className="text-lg font-semibold uppercase"
              >
                {subforum.name}
              </Link>
              <p className="!mt-3 text-base">{subforum.description}</p>
            </div>
          </div>
          {/* Forum Topics Table */}
          <div className="bg-white dark:!bg-[var(--main-white)] rounded-lg long-shadow overflow-hidden">
            <table className="min-w-full">
              <thead className="bg-gray-100 dark:!bg-neutral-600">
                <tr>
                  <th className="!p-3 text-left text-xs font-medium uppercase tracking-wider">
                    Tiêu đề
                  </th>
                  <th className="!p-3 text-center text-xs font-medium uppercase tracking-wider hidden sm:table-cell min-w-[75px]">
                    Trả lời
                  </th>
                  <th className="!p-3 text-center text-xs font-medium uppercase tracking-wider hidden sm:table-cell">
                    Xem
                  </th>
                  <th className="sm:!p-3 pr-3 text-right text-xs font-medium uppercase tracking-wider min-w-[115px] max-w-[200px]">
                    Bài viết cuối
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-neutral-600">
                {topics.map((topic) => (
                  <tr key={topic.id} className="hover:bg-gray-50 dark:hover:bg-neutral-700">
                    <td className="!p-3 max-w-96 responsive-td">
                      <div className="flex items-center">
                        <div className="flex gap-y-2 flex-col flex-1">
                          <div className="text-sm font-medium">
                            <Link
                              href={route("posts.show", {
                                username: topic.author.username,
                                id: topic.id,
                              })}
                              className="text-green-600 hover:text-green-800 dark:hover:text-green-500"
                            >
                              {topic.title}
                            </Link>
                          </div>
                          <div className="text-sm text-gray-500 flex gap-x-2 items-center">
                            <Link
                              className="flex items-center gap-x-2"
                              href={route("profile.show", { username: topic.author.username })}
                            >
                              <img
                                className="h-6 w-6 rounded-full border"
                                src={`https://api.chuyenbienhoa.com/v1.0/users/${topic.author.username}/avatar`}
                                alt="Avatar"
                              />
                              {topic.author.profile_name}
                              {topic.author.role === "admin" && (
                                <svg
                                  stroke="currentColor"
                                  fill="currentColor"
                                  strokeWidth={0}
                                  viewBox="0 0 20 20"
                                  aria-hidden="true"
                                  className="text-base leading-5 -ml-1.5 text-green-600"
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
                            <span className="-ml-1"> · {topic.created_at}</span>
                          </div>
                          <div className="text-sm text-gray-500 flex sm:hidden">
                            <div className="flex-1">
                              Trả lời: <span className="text-black">{topic.reply_count}</span> ·
                              Xem: <span className="text-black">{topic.view_count}</span>
                            </div>
                            <div>{topic.latest_reply?.created_at || topic.created_at}</div>
                          </div>
                        </div>
                      </div>
                    </td>
                    <td className="!p-3 text-center text-sm text-gray-500 hidden sm:table-cell">
                      {topic.reply_count}+
                    </td>
                    <td className="!p-3 text-center text-sm text-gray-500 hidden sm:table-cell">
                      {topic.view_count}
                    </td>
                    <td className="!p-3 text-right text-sm text-gray-500 hidden sm:table-cell">
                      <Link
                        href={route("profile.show", {
                          username: topic.latest_reply?.user.username || topic.author.username,
                        })}
                        className="hidden sm:inline"
                      >
                        <span>@</span>
                        {topic.latest_reply?.user.username || topic.author.username}
                      </Link>
                      <div>{topic.latest_reply?.created_at || topic.created_at}</div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
