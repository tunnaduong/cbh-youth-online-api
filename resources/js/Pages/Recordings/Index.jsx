import HomeLayout from "@/Layouts/HomeLayout";
import { Head } from "@inertiajs/react";
import { useState } from "react";
import RecordingItem from "./Partials/RecordingItem";

export default function Index({ recordings }) {
  const [showFullContent, setShowFullContent] = useState(false);
  const maxLength = 300; // Số ký tự tối đa trước khi truncate

  const toggleShowFullContent = (e) => {
    e.preventDefault();
    setShowFullContent(!showFullContent);
  };

  const truncateHtml = (html, maxLength) => {
    // Tạo một temporary div để parse HTML
    const tempDiv = document.createElement("div");
    tempDiv.innerHTML = html;

    // Lấy text content (không có HTML tags)
    const textContent = tempDiv.textContent || tempDiv.innerText || "";

    if (textContent.length <= maxLength) {
      return html;
    }

    // Truncate text và thêm dấu ...
    const truncatedText = textContent.substring(0, maxLength);
    const lastSpaceIndex = truncatedText.lastIndexOf(" ");
    const finalText =
      lastSpaceIndex > 0 ? truncatedText.substring(0, lastSpaceIndex) : truncatedText;

    // Trả về HTML đã được truncate (giữ lại formatting cơ bản)
    tempDiv.textContent = finalText + "...";
    return tempDiv.innerHTML;
  };

  const getContentWithReadMore = () => {
    const textContent = recording.content?.replace(/<[^>]*>/g, ""); // Remove HTML tags để đếm text
    const needsTruncation = textContent?.length > maxLength;

    if (!needsTruncation) {
      return recording.content;
    }

    let content;
    if (showFullContent) {
      content = recording.content;
    } else {
      content = truncateHtml(recording.content, maxLength);
    }

    const readMoreLink = showFullContent
      ? ' <span class="text-[var(--tw-prose-body)] dark:text-[rgb(209_213_219)] hover:underline text-base font-medium read-more-link cursor-pointer">Thu gọn</span>'
      : ' <span class="text-[var(--tw-prose-body)] dark:text-[rgb(209_213_219)] hover:underline text-base font-medium read-more-link cursor-pointer">Xem thêm</span>';

    return content + readMoreLink;
  };

  console.log(recordings);
  return (
    <HomeLayout activeBar={"recordings"}>
      <Head title="Loa lớn" />

      <div className="p-6 xl:min-h-screen">
        {recordings.map((recording) => (
          <RecordingItem recording={recording} />
        ))}
      </div>
    </HomeLayout>
  );
}
