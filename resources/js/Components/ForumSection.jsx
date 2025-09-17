import { CheckmarkCircle, Chatbubbles } from "react-ionicons";
import moment from "moment";
import "moment/locale/vi";
import { Link } from "@inertiajs/react";

// Set Vietnamese locale globally
moment.locale("vi", {
  relativeTime: {
    future: "%s tới",
    past: "%s trước",
    s: "vài giây",
    ss: "%d giây",
    m: "1 phút",
    mm: "%d phút",
    h: "1 giờ",
    hh: "%d giờ",
    d: "1 ngày",
    dd: "%d ngày",
    M: "1 tháng",
    MM: "%d tháng",
    y: "1 năm",
    yy: "%d năm",
  },
});

export default function ForumSection({ mainCategories }) {
  // console.log(mainCategories);
  return (
    <div className="max-w-[775px] w-[100%]">
      {mainCategories.map((category) => (
        <>
          <Link href={`/forum/${category.slug}`} className="text-lg font-semibold px-4 uppercase">
            {category.name}
          </Link>
          <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2 mb-6">
            {category.subforums.map((subforum, index) => (
              <>
                <div className="flex flex-row items-center min-h-[78px] pr-2">
                  <Chatbubbles color="#319528" height="32px" width="32px" className="p-4" />
                  <div className="flex flex-col flex-1">
                    <Link
                      href={`/forum/${category.slug}/${subforum.slug}`}
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
                  {/* Mới nhất */}
                  {subforum.topics.length !== 0 && (
                    <div
                      style={{ maxWidth: "calc(42%)" }}
                      className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                    >
                      <div className="flex">
                        <span className="whitespace-nowrap mr-1">Mới nhất:</span>
                        <Link
                          href={`/forum/${category.slug}/${subforum.slug}/${subforum.topics[0]?.id}`}
                          className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                        >
                          {subforum.topics[0]?.title}
                        </Link>
                      </div>
                      <div className="flex items-center mt-1 text-[#319528]">
                        <Link
                          href={`/${subforum.topics[0]?.user?.username}`}
                          className="hover:text-[#319528] hover:underline truncate"
                        >
                          {subforum.topics[0]?.user?.profile?.profile_name ||
                            subforum.topics[0]?.user?.username}
                        </Link>
                        {subforum.topics[0]?.user?.profile?.verified == "1" && (
                          <CheckmarkCircle
                            color="#319528"
                            height="15px"
                            width="15px"
                            className="ml-0.5 shrink-0"
                          />
                        )}
                        <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                          ,{" "}
                          {subforum.topics[0]?.created_at
                            ? moment(subforum.topics[0].created_at).fromNow()
                            : ""}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
                {index !== category.subforums.length - 1 && <hr />}
              </>
            ))}
          </div>
        </>
      ))}
    </div>
  );
}
