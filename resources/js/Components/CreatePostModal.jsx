import React from "react";
import { Modal, Input, Button, Select } from "antd";
import CustomInput from "./ui/Input";
import CustomColorButton from "./ui/CustomColorButton";
import { usePage } from "@inertiajs/react";
import VerifiedBadge from "./ui/VerifiedBadge";
import { IoEarth, IoCaretDown } from "react-icons/io5";
import { FaMarkdown } from "react-icons/fa";
import { FaFileLines } from "react-icons/fa6";

const CreatePostModal = ({ open, onClose }) => {
  const { forum_data, auth } = usePage().props;
  console.log(forum_data);
  return (
    <>
      <Modal
        closable={{ "aria-label": "Custom Close Button" }}
        open={open}
        onOk={onClose}
        onCancel={onClose}
        footer={null}
      >
        <div>
          <div className="flex flex-row justify-center items-center pb-[34px] relative">
            <h1 className="text-lg font-bold text-center absolute -top-1.5">Tạo cuộc thảo luận</h1>
          </div>
          <hr className="absolute right-0 left-0 w-full" />
          <div className="flex flex-row items-center py-3">
            <img
              src={`https://api.chuyenbienhoa.com/v1.0/users/${auth?.user?.username}/avatar`}
              alt={auth?.user?.username}
              className="border w-11 h-11 rounded-full"
            />
            <div className="flex flex-col ml-2">
              <span className="text-base font-semibold mb-0.5 flex items-center">
                {auth?.user?.profile?.profile_name}
                {auth?.user?.profile?.verified && <VerifiedBadge />}
              </span>
              <button className="flex items-center bg-gray-200 dark:bg-neutral-500 gap-x-0.5 rounded-md px-1.5 py-0.5 cursor-pointer w-max">
                <IoEarth className="text-base mt-[1px]" />
                <span className="text-sm font-semibold">Công khai</span>
                <IoCaretDown className="text-[9px] mt-[1px]" />
              </button>
            </div>
          </div>
          <form action="/" method="POST" className="flex flex-col space-y-4">
            <CustomInput placeholder="Tiêu đề bài viết" />
            <div className="rounded-md border shadow-sm pb-2 bg-gray-100 dark:bg-neutral-600 dark:!border-neutral-500">
              <div className="relative -mx-[1px]">
                <Input.TextArea
                  id="postDescription"
                  name="content"
                  className="bg-white dark:!bg-[#3c3c3c]"
                  placeholder="Nội dung bài viết"
                  spellCheck="false"
                  data-ms-editor="true"
                  defaultValue={null}
                  rows={5}
                />
              </div>
              <div className="px-3 flex items-center gap-x-2 mt-3">
                <a
                  href="/Admin/posts/213057"
                  className="-mt-1.5 text-xs font-bold border-right pr-2 flex items-center"
                  target="_blank"
                >
                  <FaMarkdown className="mr-1" />
                  Hỗ trợ Markdown
                </a>
                <a
                  href="/Admin/posts/213054"
                  className="-mt-1.5 text-xs font-bold flex items-center"
                  target="_blank"
                >
                  <FaFileLines className="mr-1" />
                  Quy tắc
                </a>
              </div>
            </div>
            <Select
              defaultValue="Chọn chuyên mục phù hợp"
              style={{ width: "100%" }}
              options={forum_data.main_categories.map((category) => ({
                label: <span>{category.name}</span>,
                title: category.name,
                options: category.sub_forums.map((subforum) => ({
                  label: <span>{subforum.name}</span>,
                  value: subforum.id,
                })),
              }))}
              placeholder="Chọn chuyên mục phù hợp"
              className="shadow-sm"
            />
            <img
              id="imagePreview"
              alt="Preview"
              className="border rounded-md hidden dark:!border-neutral-500"
              style={{ width: 100, height: 100, objectFit: "cover" }}
            />
            <div className="flex flex-row items-center rounded-lg border dark:!border-neutral-500 p-3 shadow-sm">
              <p className="text-sm font-medium flex-1">Thêm ảnh vào bài viết của bạn</p>
              <input id="fileInput" accept="image/*" type="file" style={{ display: "none" }} />
              <div className="flex gap-1">
                <Button size="small" className="h-8 px-2 rounded-full border-0">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={2}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="lucide lucide-image h-4 w-4 text-emerald-500"
                  >
                    <rect width={18} height={18} x={3} y={3} rx={2} ry={2}></rect>
                    <circle cx={9} cy={9} r={2} />
                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                  </svg>
                </Button>
              </div>
            </div>
            <CustomColorButton
              block
              bgColor="#318527"
              className="text-base font-semibold py-[19px] mb-1.5 hidden xl:flex"
              type="submit"
              disabled={false}
            >
              Đăng
            </CustomColorButton>
          </form>
        </div>
      </Modal>
    </>
  );
};

export default CreatePostModal;
