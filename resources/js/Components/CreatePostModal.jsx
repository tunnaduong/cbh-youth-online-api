import React from "react";
import { Modal, Input, Button, Select } from "antd";
import CustomInput from "./ui/Input";
import CustomColorButton from "./ui/CustomColorButton";

const CreatePostModal = ({ open, onClose }) => {
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
            <h1 className="text-lg font-bold text-center absolute -top-1">Tạo bài viết</h1>
          </div>
          <hr className="absolute right-0 left-0 w-full" />
          <div className="flex flex-row items-center py-3">
            <img
              src="https://api.chuyenbienhoa.com/v1.0/users/tunnaduong/avatar"
              alt="tunnaduong's avatar"
              className="border w-11 h-11 rounded-full"
            />
            <div className="flex flex-col ml-2">
              <span className="text-base font-semibold mb-0.5 flex items-center">
                Dương Tùng Anh (Tunna Duong)
                <svg
                  stroke="currentColor"
                  fill="currentColor"
                  strokeWidth={0}
                  viewBox="0 0 20 20"
                  aria-hidden="true"
                  className="text-base ml-0.5 text-green-600 mt-0.5"
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
              <button className="flex items-center bg-gray-200 dark:bg-neutral-500 rounded-md px-1.5 py-0.5 cursor-pointer w-max">
                <ion-icon
                  name="earth"
                  className="text-base mt-[1px] mr-0.5 md hydrated"
                  role="img"
                  aria-label="earth"
                >
                  <template shadowrootmode="open" />
                </ion-icon>
                <span className="text-sm font-semibold">Công khai</span>
                <ion-icon
                  name="caret-down-outline"
                  className="text-[9px] mt-[1px] ml-0.5 md hydrated"
                  role="img"
                  aria-label="caret down outline"
                >
                  <template shadowrootmode="open" />
                </ion-icon>
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
                  className="-mt-1.5 text-xs font-bold block border-right pr-2"
                  target="_blank"
                >
                  <i className="fa-brands fa-markdown mr-1" aria-hidden="true" />
                  Hỗ trợ Markdown
                </a>
                <a
                  href="/Admin/posts/213054"
                  className="-mt-1.5 text-xs font-bold block"
                  target="_blank"
                >
                  <i className="fa-solid fa-file-lines mr-1" aria-hidden="true" />
                  Quy tắc
                </a>
              </div>
            </div>
            <Select
              defaultValue="Chọn chuyên mục phù hợp"
              style={{ width: "100%" }}
              options={[
                { value: "jack", label: "Jack" },
                { value: "lucy", label: "Lucy" },
                { value: "Yiminghe", label: "yiminghe" },
                { value: "disabled", label: "Disabled", disabled: true },
              ]}
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
                <button
                  className="inline-flex items-center justify-center gap-2 whitespace-nowrap text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 hover:bg-slate-100 dark:hover:bg-neutral-500 hover:text-accent-foreground h-9 w-9 shrink-0 rounded-full"
                  type="button"
                  id="selectImage"
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
                    className="lucide lucide-image h-5 w-5 text-emerald-500"
                  >
                    <rect width={18} height={18} x={3} y={3} rx={2} ry={2}></rect>
                    <circle cx={9} cy={9} r={2} />
                    <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21" />
                  </svg>
                </button>
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
