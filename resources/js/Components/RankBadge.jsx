import React from "react";

const RankBadge = ({ index }) => {
    const rankBg = [
        "bg-red-600",
        "bg-red-400 dark:bg-[#b04848]",
        "bg-red-200 dark:bg-[#683f3f]",
    ];

    const rankText = index <= 2 ? "text-white" : "text-green-600";
    const bgColor = rankBg[index] ?? "bg-gray-200 dark:bg-[#282828]";

    return (
        <div className="pr-2 align-top text-center w-8 flex items-center">
            <span
                className={`inline-flex items-center justify-center h-5 w-5 rounded-full ${bgColor} ${rankText} text-[11px] font-medium`}
            >
                {index + 1}
            </span>
        </div>
    );
};

export default RankBadge;
