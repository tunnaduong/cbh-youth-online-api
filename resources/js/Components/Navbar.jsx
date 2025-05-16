export default function Navbar() {
    return (
        <nav className="fixed w-[100%] top-0 bg-white dark:!bg-neutral-700 shadow-md leading-[0] flex justify-between">
            <div className="flex flex-row px-6 py-3.5">
                <button
                    className="inline-flex dark:!border-neutral-500 items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 border border-input shadow-sm h-9 w-9 xl:hidden mr-3 min-w-[36px]"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasMenu"
                    aria-controls="offcanvasMenu"
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
                        className="lucide lucide-menu h-6 w-6"
                    >
                        <line x1={4} x2={20} y1={12} y2={12} />
                        <line x1={4} x2={20} y1={6} y2={6} />
                        <line x1={4} x2={20} y1={18} y2={18} />
                    </svg>
                </button>
                <a id="logo" className="inline-block" href="/">
                    <div className="flex gap-x-1 items-center min-w-max">
                        <img
                            src="/assets/images/logo.png"
                            alt="CYO's Logo"
                            className="w-10 h-10"
                        />
                        <div className="text-[14.5px] font-light text-[#319527] leading-4 hidden xl:block">
                            <h1 className="text-[14.2px]">Diễn đàn học sinh</h1>
                            <h1 className="font-bold">Chuyên Biên Hòa</h1>
                        </div>
                        <div
                            className="bg-yellow-400 text-black text-[14px] font-semibold rounded-full !px-3 !py-3 ml-2 hidden xl:block"
                            data-bs-toggle="tooltip"
                            data-bs-placement="bottom"
                            title="Diễn đàn đang trong giai đoạn thử nghiệm"
                        >
                            <span>Beta</span>
                        </div>
                    </div>
                </a>
                <div className="max-w-52 xl:flex flex-row items-center bg-[#F7F7F7] dark:!bg-neutral-600 rounded-lg pr-1 ml-7 pl-1 hidden">
                    <input
                        type="text"
                        placeholder="Tìm kiếm"
                        className="no-outline-input w-full bg-[#F7F7F7] dark:!bg-neutral-600 text-[13px] p-2 rounded-lg pr-1"
                    />
                    <div className="bg-white dark:!bg-neutral-700 rounded-lg min-w-[30px] h-[30px] flex items-center justify-center cursor-pointer search-btn dark:!border-neutral-500">
                        <svg
                            stroke="currentColor"
                            fill="currentColor"
                            strokeWidth={0}
                            viewBox="0 0 512 512"
                            className="text-[16px] text-[#6B6B6B] dark:!text-neutral-400"
                            height="1em"
                            width="1em"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M456.69 421.39 362.6 327.3a173.81 173.81 0 0 0 34.84-104.58C397.44 126.38 319.06 48 222.72 48S48 126.38 48 222.72s78.38 174.72 174.72 174.72A173.81 173.81 0 0 0 327.3 362.6l94.09 94.09a25 25 0 0 0 35.3-35.3zM97.92 222.72a124.8 124.8 0 1 1 124.8 124.8 124.95 124.95 0 0 1-124.8-124.8z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            <div className="flex items-center">
                <div className="h-full items-center flex flex-row gap-x-3 relative nav-item">
                    <a
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 nav-active"
                        href="/"
                    >
                        Cộng đồng
                    </a>
                    <a
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 "
                        href="/report"
                    >
                        Báo cáo
                    </a>
                    <a
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 "
                        href="/lookup"
                    >
                        Tra cứu
                    </a>
                    <a
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 "
                        href="/explore"
                    >
                        Khám phá
                    </a>
                    <div className="w-[1px] -ml-5 mr-3 h-6 bg-[#e2e2e3] dark:bg-[#585858] hidden xl:block" />
                    <style
                        dangerouslySetInnerHTML={{
                            __html: "\n /* Transition styles */\n .theme-transition {\n transition: background-color 0.3s, color 0.3s, border-color 0.3s;\n }\n\n /* Dark mode styles */\n .dark {\n background-color: #2c2f2e;\n color: #f3f4f6;\n }\n\n /* Toggle transition */\n .toggle-circle {\n transition: transform 0.3s ease;\n }\n\n .dark .toggle-circle {\n transform: translateX(1.4rem);\n }\n\n .logo-white:is(.dark *) {\n filter: brightness(0) saturate(100%) invert(100%) sepia(50%) saturate(258%) hue-rotate(319deg) brightness(126%) contrast(96%);\n }\n\n .theme-toggle {\n zoom: 0.75;\n }\n",
                        }}
                    />
                    <button
                        className="theme-toggle relative hidden xl:block h-8 w-14 rounded-full border !border-neutral-500 dark:border-neutral-500 bg-gray-100 dark:bg-neutral-700 hover:!border-green-600 theme-transition"
                        aria-label="Toggle theme"
                    >
                        <div className="toggle-circle absolute top-[3px] left-1 flex h-6 w-6 items-center justify-center rounded-full bg-white dark:!bg-black shadow-sm">
                            {/* Sun icon for light mode */}
                            <i
                                data-lucide="sun"
                                className="sun-icon h-3.5 w-3.5 text-black"
                            />
                            {/* Moon icon for dark mode (hidden initially) */}
                            <i
                                data-lucide="moon"
                                className="moon-icon h-3.5 w-3.5 text-black dark:!text-white hidden"
                            />
                        </div>
                        <span className="sr-only">Toggle theme</span>
                    </button>
                    <div className="w-[1px] h-6 ml-3 mr-6 bg-[#e2e2e3] dark:bg-[#585858] hidden xl:block" />
                </div>
                <div className="min-w-max mr-4">
                    <a
                        href="/login"
                        className="flex items-center gap-x-1 text-sm font-medium transition-colors duration-200 text-[#319527] hover:text-[#3dbb31]"
                        style={{ borderBottom: "3px solid transparent" }}
                    >
                        <ion-icon
                            name="log-in-outline"
                            className="text-[20px] flex-shrink-0"
                        />
                        <span className="flex-shrink-0">Đăng nhập/Đăng ký</span>
                    </a>
                </div>
                <div
                    className="offcanvas offcanvas-start max-w-72 dark:bg-[var(--main-white)]"
                    tabIndex={-1}
                    id="offcanvasMenu"
                    aria-labelledby="offcanvasMenuLabel"
                >
                    <div className="offcanvas-header">
                        <h5 className="offcanvas-title" id="offcanvasMenuLabel">
                            Menu
                        </h5>
                        <button
                            type="button"
                            className="btn-close text-reset dark:invert"
                            data-bs-dismiss="offcanvas"
                            aria-label="Close"
                        />
                    </div>
                    <div className="offcanvas-body px-0 pt-0 text-gray-700 dark:text-gray-300">
                        <nav>
                            <a
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/"
                            >
                                <i className="fa-solid fa-user-group mr-3" />{" "}
                                Cộng đồng
                            </a>
                            <ul className="pl-8">
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/"
                                    >
                                        <ion-icon
                                            name="chatbox-ellipses"
                                            className="mr-3"
                                        />{" "}
                                        Diễn đàn
                                    </a>
                                </li>
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/feed"
                                    >
                                        <ion-icon
                                            name="telescope"
                                            className="mr-3"
                                        />{" "}
                                        Bảng tin
                                    </a>
                                </li>
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/recordings"
                                    >
                                        <ion-icon
                                            name="megaphone"
                                            className="mr-3"
                                        />{" "}
                                        Loa lớn
                                    </a>
                                </li>
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/youth-news"
                                    >
                                        <ion-icon
                                            name="newspaper"
                                            className="mr-3"
                                        />{" "}
                                        Tin tức Đoàn
                                    </a>
                                </li>
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/saved"
                                    >
                                        <ion-icon
                                            name="bookmark"
                                            className="mr-3"
                                        />{" "}
                                        Đã lưu
                                    </a>
                                </li>
                            </ul>
                            <a
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/report"
                            >
                                <i className="fa-solid fa-flag mr-3" /> Báo cáo
                            </a>
                            <ul className="pl-8">
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/report/class"
                                    >
                                        <ion-icon
                                            name="people"
                                            className="mr-3"
                                        />{" "}
                                        Báo cáo tập thể lớp
                                    </a>
                                </li>
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/report/student"
                                    >
                                        <ion-icon
                                            name="person"
                                            className="mr-3"
                                        />{" "}
                                        Báo cáo học sinh
                                    </a>
                                </li>
                            </ul>
                            <a
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/lookup"
                            >
                                <i className="fa-solid fa-magnifying-glass mr-3" />{" "}
                                Tra cứu
                            </a>
                            <ul className="pl-8">
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/lookup/timetable"
                                    >
                                        <ion-icon
                                            name="calendar"
                                            className="mr-3"
                                        />{" "}
                                        Thời khóa biểu
                                    </a>
                                </li>
                                <li>
                                    <a
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/lookup/class-ranking"
                                    >
                                        <ion-icon
                                            name="trophy"
                                            className="mr-3"
                                        />{" "}
                                        Xếp hạng thi đua lớp
                                    </a>
                                </li>
                            </ul>
                            <a
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/explore"
                            >
                                <ion-icon name="apps" className="mr-3" /> Khám
                                phá
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
        </nav>
    );
}
