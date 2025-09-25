import HomeLayout from "@/Layouts/HomeLayout";
import { Chatbubbles } from "react-ionicons";
import { Head, Link } from "@inertiajs/react";
import { generatePostSlug } from "@/Utils/slugify";
import React from "react";
import { moment } from "@/Utils/momentConfig";
import VerifiedBadge from "@/Components/ui/Badges";

export default function Category({ category }) {
  console.log(category);
  return (
    <HomeLayout activeNav="home">
      <Head title={category.name} />
      <div className="px-2.5 xl:min-h-screen py-6 flex justify-center">
        <div className="max-w-[775px] w-[100%] mb-6">
          {/* Breadcrumb */}
          <nav aria-label="breadcrumb">
            <ol className="flex items-center space-x-2 text-sm px-1.5">
              <li className="flex items-center">
                <Link
                  href="/"
                  className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-base"
                >
                  Diễn đàn
                </Link>
              </li>
              <li className="flex items-center">
                <span className="mr-2 text-gray-400">/</span>
                <span className="text-gray-900 dark:text-gray-100 text-base" aria-current="page">
                  {category.name}
                </span>
              </li>
            </ol>
          </nav>
          <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2 p-4 relative z-10 overflow-hidden">
            <div>
              <div
                className="w-[50%] absolute h-full mb-4 top-0 right-0 -z-10"
                style={{
                  backgroundImage: `url("/images/${category.background_image}")`,
                  backgroundSize: "cover",
                  backgroundPosition: "center",
                }}
              ></div>
              <div className="fade-to-left" />
            </div>
            <Link
              href={route("forum.category", { category: category.slug })}
              className="text-lg font-semibold uppercase"
            >
              {category.name}
            </Link>
            <p className="!mt-3 text-base">{category.description}</p>
          </div>
          <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg !mt-5">
            {category.subforums.map((subforum, index) => (
              <>
                <div className="flex flex-row items-center min-h-[78px] pr-2">
                  <Chatbubbles color="#319528" height="32px" width="32px" className="p-4" />
                  <div className="flex flex-col flex-1">
                    <Link
                      href={route("forum.subforum", {
                        category: category.slug,
                        subforum: subforum.slug,
                      })}
                      className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                    >
                      {subforum.name}
                    </Link>
                    <span className="text-sm text-gray-500">
                      Bài viết:{" "}
                      <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                        {subforum.topics_count}
                      </span>
                      Bình luận:{" "}
                      <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                        {subforum.comments_count}
                      </span>
                    </span>
                  </div>
                  {subforum.latest_topic && (
                    <div
                      style={{ maxWidth: "calc(42%)" }}
                      className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                    >
                      <div className="flex">
                        <span className="whitespace-nowrap mr-1">Mới nhất:</span>
                        <Link
                          href={route("posts.show", {
                            username: subforum.latest_topic?.user?.username,
                            id: generatePostSlug(
                              subforum.latest_topic?.id,
                              subforum.latest_topic?.title
                            ),
                          })}
                          className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                        >
                          {subforum.latest_topic?.title}
                        </Link>
                      </div>
                      <div className="flex items-center mt-1 text-[#319528]">
                        {subforum.latest_topic?.anonymous ? (
                          <span className="hover:text-[#319528] truncate">Người dùng ẩn danh</span>
                        ) : (
                          <>
                            <Link
                              href={route("profile.show", {
                                username: subforum.latest_topic?.user?.username,
                              })}
                              className="hover:text-[#319528] hover:underline truncate"
                            >
                              {subforum.latest_topic?.user?.profile?.profile_name ||
                                subforum.latest_topic?.user?.username}
                            </Link>
                            {subforum.latest_topic?.user?.profile?.verified == "1" && (
                              <VerifiedBadge />
                            )}
                          </>
                        )}
                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                          , {moment(subforum.latest_topic?.created_at).fromNow()}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
                {index !== category.subforums.length - 1 && <hr />}
              </>
            ))}
          </div>
        </div>
      </div>
    </HomeLayout>
  );
}
