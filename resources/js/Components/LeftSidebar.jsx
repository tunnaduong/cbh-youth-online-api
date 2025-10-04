import { Link, usePage, router } from "@inertiajs/react";
import { message } from "antd";
import {
  ChatboxEllipses,
  Telescope,
  Megaphone,
  Newspaper,
  Bookmark,
  HelpCircle,
  Chatbubbles,
} from "react-ionicons";

export default function LeftSidebar({
  activeBar = "forum",
  type = "default",
  items = [
    {
      key: "forum",
      href: "/",
      label: "Diễn đàn",
      Icon: ChatboxEllipses,
      isExternal: false,
    },
    {
      key: "feed",
      href: "/feed",
      label: "Bảng tin",
      Icon: Telescope,
    },
    {
      key: "recordings",
      href: "/recordings",
      label: "Loa lớn",
      Icon: Megaphone,
    },
    {
      key: "news",
      href: "/youth-news",
      label: "Tin tức Đoàn",
      Icon: Newspaper,
    },
    {
      key: "saved",
      href: "/saved",
      label: "Đã lưu",
      Icon: Bookmark,
    },
    // help and feedback later
    {
      key: "help",
      href: route("help.index"),
      label: "Trợ giúp",
      Icon: HelpCircle,
    },
    {
      key: "feedback",
      href: "https://forms.gle/XJ3v1vN82BxLUVWo9",
      label: "Góp ý",
      Icon: Chatbubbles,
      isExternal: true,
    },
  ],
}) {
  const { auth } = usePage().props;
  const iconColor = "#CACACA";
  const activeIconColor = "#319527";
  const iconSize = "18px";

  const handleSavedClick = (e) => {
    if (!auth?.user) {
      e.preventDefault();
      message.error("Vui lòng đăng nhập để xem các bài viết đã lưu của bạn.");
      router.visit("/login?continue=" + encodeURIComponent("/saved"), {
        preserveScroll: true,
      });
    }
  };

  const baseLinkClass =
    "mb-3 text-base font-semibold flex items-center w-full text-left rounded-xl p-2.5";
  const activeLinkClass = "hover:text-[#319527] text-[#319527] bg-[#E4EEE3] dark:bg-[#495648]";
  const inactiveLinkClass =
    "text-[#6B6B6B] dark:text-[#CACACA] hover:text-[#6B6B6B] dark:hover:text-white";

  const activeIconWrapper =
    "text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center !border-[#BFE5BB] dark:!border-[#4f7b50] bg-[#CDEBCA] dark:bg-[#1d2a1c]";
  const inactiveIconWrapper =
    "text-lg rounded-lg w-[30px] h-[30px] mr-3 menu-border flex items-center justify-center border-[#ECECEC] dark:!border-neutral-500";

  return (
    <>
      {/* Left side bar */}
      <div
        className="w-[260px] hidden xl:flex flex-col !p-6 sticky top-[69px] h-min"
        id="left-sidebar"
      >
        <p className="text-sm font-semibold text-[#6b6b6b] dark:text-neutral-400 pb-3 ml-2.5">
          MENU
        </p>

        {(type === "default" ? items.slice(0, 5) : items).map((it) => {
          const isActive = activeBar === it.key;
          const LinkComp = it.isExternal ? "a" : Link;
          const props = it.isExternal
            ? { href: it.href, target: "_blank", rel: "noopener noreferrer" }
            : { href: it.href };

          // Add click handler for saved posts
          const handleClick = it.key === "saved" ? handleSavedClick : undefined;

          return (
            <LinkComp
              key={it.key}
              {...props}
              onClick={handleClick}
              className={`${baseLinkClass} ${isActive ? activeLinkClass : inactiveLinkClass}`}
            >
              <div className={isActive ? activeIconWrapper : inactiveIconWrapper}>
                <it.Icon
                  color={isActive ? activeIconColor : iconColor}
                  height={iconSize}
                  width={iconSize}
                />
              </div>
              <div className={isActive ? "text-[#319527]" : "text-[#6B6B6B] dark:text-[#CACACA]"}>
                {it.label}
              </div>
            </LinkComp>
          );
        })}

        {type === "default" && (
          <>
            <hr className="my-3 dark:border-neutral-600" />

            {items
              .filter((it) => it.key === "help")
              .map((it) => {
                const isActive = activeBar === it.key;
                return (
                  <Link
                    key={it.key}
                    href={it.href}
                    className={`${baseLinkClass} ${isActive ? activeLinkClass : inactiveLinkClass}`}
                  >
                    <div className={isActive ? activeIconWrapper : inactiveIconWrapper}>
                      <it.Icon
                        color={isActive ? activeIconColor : iconColor}
                        height={iconSize}
                        width={iconSize}
                      />
                    </div>
                    <div
                      className={isActive ? "text-[#319527]" : "text-[#6B6B6B] dark:text-[#CACACA]"}
                    >
                      {it.label}
                    </div>
                  </Link>
                );
              })}

            {items
              .filter((it) => it.key === "feedback")
              .map((it) => (
                <a
                  key={it.key}
                  href={it.href}
                  className={`${baseLinkClass} ${inactiveLinkClass}`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <div className={inactiveIconWrapper}>
                    <it.Icon color={iconColor} height={iconSize} width={iconSize} />
                  </div>
                  <div className="text-[#6B6B6B] dark:text-[#CACACA]">{it.label}</div>
                </a>
              ))}
          </>
        )}
      </div>
    </>
  );
}
