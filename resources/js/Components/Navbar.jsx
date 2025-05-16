import { Link } from "@inertiajs/react";
import DarkmodeToggle from "./DarkmodeToggle";
import { useTheme } from "@/Hooks/useTheme";
import { Sun, Moon } from "lucide-react";
import {
    LogInOutline,
    ChatboxEllipsesOutline,
    TelescopeOutline,
    MegaphoneOutline,
    NewspaperOutline,
    BookmarkOutline,
    PeopleOutline,
    PersonOutline,
    CalendarOutline,
    TrophyOutline,
    AppsOutline,
} from "react-ionicons";

export default function Navbar() {
    const { theme, toggleTheme } = useTheme();

    const ioniconDefaultColor = theme === "dark" ? "#FFF" : "#000";
    const ioniconSize = "20px";

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
                <Link id="logo" className="inline-block" href="/">
                    <div className="flex gap-x-1 items-center min-w-max">
                        <img
                            src="/images/logo.png"
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
                </Link>
                <div className="max-w-52 xl:flex flex-row items-center bg-[#F7F7F7] dark:!bg-neutral-600 rounded-lg pr-1 ml-7 pl-1 hidden">
                    <input
                        type="text"
                        placeholder="Tìm kiếm"
                        className="border-0 w-full bg-[#F7F7F7] dark:!bg-neutral-600 text-[13px] p-2 rounded-lg pr-1"
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
                    <Link
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 nav-active"
                        href="/"
                    >
                        Cộng đồng
                    </Link>
                    <Link
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 "
                        href="/report"
                    >
                        Báo cáo
                    </Link>
                    <Link
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 "
                        href="/lookup"
                    >
                        Tra cứu
                    </Link>
                    <Link
                        className="xl:flex px-3 py-2 mr-5 dark:text-neutral-300 dark:hover:text-white hidden h-full items-center min-w-max text-center text-sm font-medium transition-colors duration-200 "
                        href="/explore"
                    >
                        Khám phá
                    </Link>
                    <div className="w-[1px] -ml-5 mr-3 h-6 bg-[#e2e2e3] dark:bg-[#585858] hidden xl:block" />
                    <DarkmodeToggle />
                    <div className="w-[1px] h-6 ml-3 mr-6 bg-[#e2e2e3] dark:bg-[#585858] hidden xl:block" />
                </div>
                <div className="min-w-max mr-4">
                    <Link
                        href="/login"
                        className="flex items-center gap-x-1 text-sm font-medium transition-colors duration-200 text-[#319527] hover:text-[#3dbb31]"
                        style={{ borderBottom: "3px solid transparent" }}
                    >
                        <LogInOutline
                            color={theme === "dark" ? "#3dbb31" : "#319527"}
                            height={ioniconSize}
                            width={ioniconSize}
                            cssClasses="flex-shrink-0"
                        />
                        <span className="flex-shrink-0">Đăng nhập/Đăng ký</span>
                    </Link>
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
                            <Link
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/"
                            >
                                <i className="fa-solid fa-user-group mr-3" />{" "}
                                Cộng đồng
                            </Link>
                            <ul className="pl-8">
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/"
                                    >
                                        <ChatboxEllipsesOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Diễn đàn
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/feed"
                                    >
                                        <TelescopeOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Bảng tin
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/recordings"
                                    >
                                        <MegaphoneOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Loa lớn
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/youth-news"
                                    >
                                        <NewspaperOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Tin tức Đoàn
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/saved"
                                    >
                                        <BookmarkOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Đã lưu
                                    </Link>
                                </li>
                            </ul>
                            <Link
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/report"
                            >
                                <i className="fa-solid fa-flag mr-3" /> Báo cáo
                            </Link>
                            <ul className="pl-8">
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/report/class"
                                    >
                                        <PeopleOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Báo cáo tập thể lớp
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/report/student"
                                    >
                                        <PersonOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Báo cáo học sinh
                                    </Link>
                                </li>
                            </ul>
                            <Link
                                className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                href="/lookup"
                            >
                                <i className="fa-solid fa-magnifying-glass mr-3" />{" "}
                                Tra cứu
                            </Link>
                            <ul className="pl-8">
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/lookup/timetable"
                                    >
                                        <CalendarOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Thời khóa biểu
                                    </Link>
                                </li>
                                <li>
                                    <Link
                                        className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                        href="/lookup/class-ranking"
                                    >
                                        <TrophyOutline
                                            color={ioniconDefaultColor}
                                            height={ioniconSize}
                                            width={ioniconSize}
                                            cssClasses="mr-3"
                                        />{" "}
                                        Xếp hạng thi đua lớp
                                    </Link>
                                </li>
                            </ul>
                            <li>
                                <Link
                                    className="flex items-center px-4 py-3 hover:bg-gray-100 dark:hover:bg-neutral-500 text-base active:bg-green-600 active:text-white"
                                    href="/explore"
                                >
                                    <AppsOutline
                                        color={ioniconDefaultColor}
                                        height={ioniconSize}
                                        width={ioniconSize}
                                        cssClasses="mr-3"
                                    />{" "}
                                    Khám phá
                                </Link>
                            </li>
                        </nav>
                    </div>
                </div>
            </div>
        </nav>
    );
}
