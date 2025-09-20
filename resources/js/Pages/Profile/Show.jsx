import { Head } from "@inertiajs/react";
import DefaultLayout from "@/Layouts/DefaultLayout";
import PostItem from "@/Components/PostItem";

export default function Show({ profile }) {
  console.log(profile);
  return (
    <DefaultLayout activeNav="home">
      <Head title={profile.profile_name} />
      <div className="xl:min-h-screen">
        <div className="flex-1">
          <div className="relative h-min lg:h-56 overflow-hidden px-2.5 py-8">
            <div
              style={{
                backgroundImage: `url(https://api.chuyenbienhoa.com/v1.0/users/${profile.username}/avatar)`,
              }}
              className="bg-gray-300 w-full h-[450px] lg:h-56 blur-effect"
            />
            <div className="lg:hidden flex flex-col items-center gap-y-2 relative z-10">
              <a href={`https://api.chuyenbienhoa.com/v1.0/users/${profile.username}/avatar`}>
                <img
                  className="w-32 h-32 rounded-full bg-white"
                  style={{ border: "4px solid #eeeeee" }}
                  src={`https://api.chuyenbienhoa.com/v1.0/users/${profile.username}/avatar`}
                  alt="avatar"
                />
              </a>
              <div className="flex flex-col items-center">
                <h1 className="font-bold text-xl mt-2 text-center">
                  <span>
                    {profile.profile_name}
                    <span>
                      <svg
                        stroke="currentColor"
                        fill="currentColor"
                        strokeWidth={0}
                        viewBox="0 0 20 20"
                        aria-hidden="true"
                        className="relative inline shrink-0 text-xl leading-5 text-green-600"
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
                    </span>
                  </span>
                </h1>
                <p className="text-sm text-gray-500">
                  <span>@</span>
                  {profile.username}
                </p>
              </div>
              <div className="flex flex-col items-center gap-y-1 !px-6">
                <div className="flex flex-wrap justify-center gap-y-1 px-3">
                  <a href="/Admin" className="px-3">
                    <span className="text-gray-500">Bài đã đăng: </span>
                    <span className="font-bold">12</span>
                  </a>
                  <div className="px-3">
                    <span className="text-gray-500">Điểm: </span>
                    <span className="font-bold">145</span>
                  </div>
                </div>
                <div className="flex flex-wrap justify-center gap-y-1">
                  <a href="/Admin/following" className="px-3">
                    <span className="text-gray-500">Đang theo dõi: </span>
                    <span className="font-bold">0</span>
                  </a>
                  <a href="/Admin/followers" className="px-3">
                    <span className="text-gray-500">Người theo dõi: </span>
                    <span className="font-bold follower_count">1</span>
                  </a>
                  <div className="px-3">
                    <span className="text-gray-500">Lượt like: </span>
                    <span className="font-bold">5</span>
                  </div>
                </div>
              </div>
              <p className="text-center">{profile.bio}</p>
              <div className="flex flex-col gap-y-2">
                <div className="flex items-center -ml-0.5 gap-x-1 text-gray-500">
                  <ion-icon
                    name="location-outline"
                    className="text-lg md hydrated"
                    role="img"
                    aria-label="location outline"
                  >
                    <template shadowrootmode="open" />
                  </ion-icon>
                  <span className="text-sm">{profile.location}</span>
                </div>
                <div className="flex items-center -ml-0.5 gap-x-1 text-gray-500">
                  <ion-icon
                    name="calendar-outline"
                    className="text-lg md hydrated"
                    role="img"
                    aria-label="calendar outline"
                  >
                    <template shadowrootmode="open" />
                  </ion-icon>
                  <span className="text-sm">{profile.joined_at}</span>
                </div>
              </div>
              <div className="flex-1 flex justify-end items-center mt-3">
                <button
                  type="button"
                  onclick="toggleFollow(45, true)"
                  className="followBtn btn btn-outline-success rounded-full px-4 hover:bg-green-600 border-green-600 hover:border-green-600 text-green-600"
                >
                  Theo dõi
                </button>
              </div>
            </div>
          </div>
          <div className="lg:bg-white dark:!bg-neutral-700 h-16 lg:shadow-md">
            <div className="mx-auto max-w-[959px] h-full lg:flex hidden">
              <a href={`https://api.chuyenbienhoa.com/v1.0/users/${profile.username}/avatar`}>
                <img
                  className="w-[170px] h-[170px] rounded-full absolute bg-white"
                  style={{
                    border: "4px solid #eeeeee",
                    transform: "translateY(-45%)",
                  }}
                  src={`https://api.chuyenbienhoa.com/v1.0/users/${profile.username}/avatar`}
                  alt="avatar"
                />
              </a>
              <div className="flex-1 min-w-[280px]" />
              <div className="flex flex-row">
                <a
                  className="select-none cursor-pointer h-full flex flex-col items-center justify-center px-3 box-border min-w-max"
                  style={{ borderBottom: "3px solid #319527" }}
                >
                  <p className="font-semibold text-sm text-slate-600 dark:text-neutral-400">
                    Bài viết
                  </p>
                  <p className="font-bold text-xl text-green-600">{profile.stats.posts}</p>
                </a>
                <a
                  href="/Admin/following"
                  className="select-none h-full flex flex-col items-center justify-center px-3 box-border min-w-max"
                  style={{ borderBottom: "3px solid transparent" }}
                >
                  <p className="font-semibold text-sm text-slate-600 dark:text-neutral-400">
                    Đang theo dõi
                  </p>
                  <p className="font-bold text-xl text-green-600">{profile.stats.following}</p>
                </a>
                <a
                  href="/Admin/followers"
                  className="select-none h-full flex flex-col items-center justify-center px-3 box-border min-w-max"
                  style={{ borderBottom: "3px solid transparent" }}
                >
                  <p className="font-semibold text-sm text-slate-600 dark:text-neutral-400">
                    Người theo dõi
                  </p>
                  <p className="font-bold text-xl text-green-600 follower_count">
                    {profile.stats.followers}
                  </p>
                </a>
                <div
                  className="select-none h-full flex flex-col items-center justify-center px-3 box-border min-w-max"
                  style={{ borderBottom: "3px solid transparent" }}
                >
                  <p className="font-semibold text-sm text-slate-600 dark:text-neutral-400">
                    Thích
                  </p>
                  <p className="font-bold text-xl text-green-600">{profile.stats.likes}</p>
                </div>
                <div
                  className="select-none h-full flex flex-col items-center justify-center px-3 box-border min-w-max"
                  style={{ borderBottom: "3px solid transparent" }}
                >
                  <p className="font-semibold text-sm text-slate-600 dark:text-neutral-400">Điểm</p>
                  <p className="font-bold text-xl text-green-600">{profile.stats.points}</p>
                </div>
              </div>
              <div className="flex-1 flex justify-end items-center">
                <button
                  type="button"
                  onclick="toggleFollow(45, true)"
                  className="followBtn btn btn-outline-success rounded-full px-4 hover:bg-green-600 border-green-600 hover:border-green-600 text-green-600"
                >
                  Theo dõi
                </button>
              </div>
            </div>
            <div className="mx-auto max-w-[959px] flex">
              <div className="max-w-[280px] flex-1 !mt-10 pr-6 hidden lg:flex flex-col gap-y-3">
                <div>
                  <h1 className="font-bold text-xl">
                    <span>
                      <span className="mr-1">{profile.profile_name}</span>
                      {(profile.verified || profile?.verified) && (
                        <span>
                          <svg
                            stroke="currentColor"
                            fill="currentColor"
                            strokeWidth={0}
                            viewBox="0 0 20 20"
                            aria-hidden="true"
                            className="relative inline shrink-0 text-xl leading-5 text-green-600"
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
                        </span>
                      )}
                    </span>
                  </h1>
                  <p className="text-sm text-gray-500">
                    <span>@</span>
                    {profile.username}
                  </p>
                </div>
                <p>{profile.bio}</p>
                <div className="flex flex-col gap-y-2">
                  <div className="flex items-center -ml-0.5 gap-x-1 text-gray-500">
                    <ion-icon
                      name="location-outline"
                      className="text-lg md hydrated"
                      role="img"
                      aria-label="location outline"
                    >
                      <template shadowrootmode="open" />
                    </ion-icon>
                    <span className="text-sm">{profile.location}</span>
                  </div>
                  <div className="flex items-center -ml-0.5 gap-x-1 text-gray-500">
                    <ion-icon
                      name="calendar-outline"
                      className="text-lg md hydrated"
                      role="img"
                      aria-label="calendar outline"
                    >
                      <template shadowrootmode="open" />
                    </ion-icon>
                    <span className="text-sm">Đã tham gia {profile.joined_at}</span>
                  </div>
                </div>
              </div>
              <div className="flex-1 !mt-6 !px-3 md:!px-0 flex flex-col items-center">
                {profile.posts.map((post) => (
                  <PostItem key={post.id} post={post} />
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>
    </DefaultLayout>
  );
}
