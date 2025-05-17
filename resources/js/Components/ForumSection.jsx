export default function ForumSection() {
    return (
        <div className="max-w-[775px] w-[100%] mb-6">
            <a
                href="/forum/thong-bao"
                className="text-lg font-semibold px-4 uppercase"
            >
                Thông báo
            </a>
            <div className="bg-white dark:!bg-[var(--main-white)] long-shadow rounded-lg mt-2">
                <div className="flex flex-row items-center min-h-[78px] pr-2">
                    <ion-icon
                        name="chatbubbles"
                        className="text-[#319528] text-3xl p-4"
                    />
                    <div className="flex flex-col flex-1">
                        <a
                            href="/forum/thong-bao/tin-tuc-tu-ban-quan-tri"
                            className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                        >
                            Tin tức từ Ban quản trị
                        </a>
                        <span className="text-sm text-gray-500">
                            Bài viết:{" "}
                            <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                6
                            </span>
                            Bình luận:{" "}
                            <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                6
                            </span>
                        </span>
                    </div>
                    <div
                        style={{ maxWidth: "calc(42%)" }}
                        className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                    >
                        <div className="flex">
                            <span className="whitespace-nowrap mr-1">
                                Mới nhất:
                            </span>
                            <a
                                href="/Admin/posts/213120"
                                className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                            >
                                🌙 CBH Youth Online chính thức ra mắt Dark Mode
                            </a>
                        </div>
                        <div className="flex items-center mt-1 text-[#319528]">
                            <a
                                href="/Admin"
                                className="hover:text-[#319528] hover:underline truncate"
                            >
                                CBH Youth Online
                            </a>
                            <ion-icon
                                name="checkmark-circle"
                                className="text-[15px] leading-5 ml-0.5 shrink-0"
                            />
                            <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                , 2 tuần trước
                            </span>
                        </div>
                    </div>
                </div>
                <hr />
                <div className="flex flex-row items-center min-h-[78px] pr-2">
                    <ion-icon
                        name="chatbubbles"
                        className="text-[#319528] text-3xl p-4"
                    />
                    <div className="flex flex-col flex-1">
                        <a
                            href="/forum/thong-bao/quy-dinh-va-huong-dan"
                            className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                        >
                            Quy định và hướng dẫn
                        </a>
                        <span className="text-sm text-gray-500">
                            Bài viết:{" "}
                            <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                3
                            </span>
                            Bình luận:{" "}
                            <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                7
                            </span>
                        </span>
                    </div>
                    <div
                        style={{ maxWidth: "calc(42%)" }}
                        className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                    >
                        <div className="flex">
                            <span className="whitespace-nowrap mr-1">
                                Mới nhất:
                            </span>
                            <a
                                href="/Admin/posts/213101"
                                className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                            >
                                Cách Tính Điểm Xếp Hạng Thành Viên Trên CBH
                                Youth Online
                            </a>
                        </div>
                        <div className="flex items-center mt-1 text-[#319528]">
                            <a
                                href="/Admin"
                                className="hover:text-[#319528] hover:underline truncate"
                            >
                                CBH Youth Online
                            </a>
                            <ion-icon
                                name="checkmark-circle"
                                className="text-[15px] leading-5 ml-0.5 shrink-0"
                            />
                            <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                , 1 tháng trước
                            </span>
                        </div>
                    </div>
                </div>
                <div className="w-full flex justify-center">
                    {/* AdSense manual ad code */}
                    <ins
                        className="adsbygoogle"
                        style={{ display: "block" }}
                        data-ad-format="fluid"
                        data-ad-layout-key="-hl+a-w-1e+66"
                        data-ad-client="ca-pub-3425905751761094"
                        data-ad-slot={6807515102}
                    />
                </div>
                <hr />
                <div className="flex flex-row items-center min-h-[78px] pr-2">
                    <ion-icon
                        name="chatbubbles"
                        className="text-[#319528] text-3xl p-4"
                    />
                    <div className="flex flex-col flex-1">
                        <a
                            href="/forum/thong-bao/tin-tuc-doan"
                            className="text-[#319528] hover:text-[#319528] text-base font-bold w-fit"
                        >
                            Tin tức Đoàn
                        </a>
                        <span className="text-sm text-gray-500">
                            Bài viết:{" "}
                            <span className="mr-1 font-semibold text-black dark:!text-[#f3f4f6]">
                                5
                            </span>
                            Bình luận:{" "}
                            <span className="text-black dark:!text-[#f3f4f6] font-semibold">
                                1
                            </span>
                        </span>
                    </div>
                    <div
                        style={{ maxWidth: "calc(42%)" }}
                        className="flex-1 bg-[#E7FFE4] dark:!bg-[#2b2d2c] dark:!border-[#545454] text-[13px] p-2 px-2 rounded-md flex-col hidden sm:flex border-all"
                    >
                        <div className="flex">
                            <span className="whitespace-nowrap mr-1">
                                Mới nhất:
                            </span>
                            <a
                                href="/DoanTruongCBH/posts/213118"
                                className="text-[#319528] hover:text-[#319528] hover:underline inline-block text-ellipsis whitespace-nowrap overflow-hidden"
                            >
                                Học sinh THPT Chuyên Biên Hoà được triệu tập
                                tham dự kỳ thi Olympic Hóa học Quốc tế
                            </a>
                        </div>
                        <div className="flex items-center mt-1 text-[#319528]">
                            <a
                                href="/DoanTruongCBH"
                                className="hover:text-[#319528] hover:underline truncate"
                            >
                                Đoàn trường THPT Chuyên Biên Hòa
                            </a>
                            <ion-icon
                                name="checkmark-circle"
                                className="text-[15px] leading-5 ml-0.5 shrink-0"
                            />
                            <span className="text-black shrink-0 dark:!text-[#f3f4f6]">
                                , 3 tuần trước
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
