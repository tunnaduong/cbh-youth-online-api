import React, { useState } from "react";
import { Modal, Input, Button, Select, message, Switch } from "antd";
import CustomInput from "./ui/Input";
import CustomColorButton from "./ui/CustomColorButton";
import { usePage, useForm } from "@inertiajs/react";
import VerifiedBadge from "./ui/Badges";
import { IoEarth, IoCaretDown } from "react-icons/io5";
import { FaMarkdown } from "react-icons/fa";
import { FaFileLines } from "react-icons/fa6";

const CreatePostModal = ({ open, onClose }) => {
  const { forum_data, auth } = usePage().props;
  const [selectedSubforum, setSelectedSubforum] = useState(null);
  const [imageFiles, setImageFiles] = useState([]);
  const [imagePreviews, setImagePreviews] = useState([]);

  const { data, setData, post, processing, errors, reset } = useForm({
    title: "",
    description: "",
    subforum_id: null,
    image_files: [],
    visibility: 0, // 0: public, 1: private
    anonymous: false, // false: normal post, true: anonymous post
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    console.log("Form data:", data);
    console.log("Auth user:", auth?.user);
    console.log("Form validation - Title:", data.title);
    console.log("Form validation - Description:", data.description);
    console.log("Form validation - Subforum:", data.subforum_id);

    // Check if user is authenticated
    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để tạo bài viết");
      return;
    }

    // Validate required fields
    if (!data.title.trim()) {
      message.error("Vui lòng nhập tiêu đề bài viết");
      return;
    }

    if (!data.description.trim()) {
      message.error("Vui lòng nhập nội dung bài viết");
      return;
    }

    post("/topics", {
      onSuccess: (page) => {
        console.log("Success:", page);
        message.success("Bài viết đã được tạo thành công!");
        reset();
        setSelectedSubforum(null);
        setImageFiles([]);
        setImagePreviews([]);
        onClose();
      },
      onError: (errors) => {
        console.error("Form errors:", errors);
        console.error("Detailed errors:", JSON.stringify(errors, null, 2));
        if (errors.image_files) {
          message.error(`Lỗi ảnh: ${errors.image_files}`);
        } else if (errors.title) {
          message.error(`Lỗi tiêu đề: ${errors.title}`);
        } else if (errors.description) {
          message.error(`Lỗi nội dung: ${errors.description}`);
        } else {
          message.error("Có lỗi xảy ra khi tạo bài viết. Vui lòng thử lại.");
        }
      },
      onFinish: () => {
        console.log("Request finished");
      },
    });
  };

  const handleImageChange = (e) => {
    const files = Array.from(e.target.files);

    if (files.length === 0) return;

    // Validate all files
    for (const file of files) {
      if (!file.type.startsWith("image/")) {
        message.error("Vui lòng chọn file ảnh hợp lệ");
        return;
      }

      if (file.size > 10 * 1024 * 1024) {
        message.error("Kích thước file không được vượt quá 10MB");
        return;
      }
    }

    // Add new files to existing ones
    const newFiles = [...imageFiles, ...files];
    setImageFiles(newFiles);
    setData("image_files", newFiles);

    // Create previews for new files
    files.forEach((file) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        setImagePreviews((prev) => [
          ...prev,
          {
            id: Date.now() + Math.random(), // Unique ID for each preview
            file: file,
            preview: e.target.result,
          },
        ]);
      };
      reader.readAsDataURL(file);
    });
  };

  const removeImage = (index) => {
    const newFiles = imageFiles.filter((_, i) => i !== index);
    const newPreviews = imagePreviews.filter((_, i) => i !== index);

    setImageFiles(newFiles);
    setImagePreviews(newPreviews);
    setData("image_files", newFiles);
  };

  const handleSubforumChange = (value) => {
    setSelectedSubforum(value);
    setData("subforum_id", value);
  };

  return (
    <>
      <Modal
        closable={{ "aria-label": "Custom Close Button" }}
        open={open}
        onOk={onClose}
        onCancel={onClose}
        footer={null}
        style={{ top: 40 }}
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
          <form onSubmit={handleSubmit} className="flex flex-col space-y-4">
            <CustomInput
              placeholder="Tiêu đề bài viết"
              value={data.title}
              onChange={(e) => setData("title", e.target.value)}
              error={errors.title}
            />
            <div className="rounded-md border shadow-sm pb-2 bg-gray-100 dark:bg-neutral-600 dark:!border-neutral-500">
              <div className="relative -mx-[1px] -mt-[1px]">
                <Input.TextArea
                  id="postDescription"
                  name="description"
                  className="!bg-white dark:!bg-[#3c3c3c]"
                  placeholder="Nội dung bài viết"
                  spellCheck="false"
                  data-ms-editor="true"
                  value={data.description}
                  onChange={(e) => setData("description", e.target.value)}
                  rows={5}
                />
                {errors.description && (
                  <div className="text-red-500 text-sm mt-1">{errors.description}</div>
                )}
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
              value={selectedSubforum}
              onChange={handleSubforumChange}
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
            {errors.subforum_id && <div className="text-red-500 text-sm">{errors.subforum_id}</div>}

            {/* Anonymous Posting Switcher */}
            <div className="flex flex-col space-y-2 shadow-sm">
              <div className="flex items-center justify-between p-3 rounded-lg border dark:!border-neutral-500 bg-gray-50 dark:bg-neutral-700">
                <div className="flex flex-col">
                  <span className="text-sm font-medium text-gray-900 dark:text-gray-100">
                    Đăng ẩn danh
                  </span>
                  <span className="text-xs text-gray-500 dark:text-gray-400">
                    Người kiểm duyệt vẫn sẽ thấy nội dung bạn đăng
                  </span>
                </div>
                <Switch
                  checked={data.anonymous}
                  onChange={(checked) => setData("anonymous", checked)}
                  size="default"
                />
              </div>
            </div>

            {imagePreviews.length > 0 && (
              <div className="grid grid-cols-4 gap-2">
                {imagePreviews.map((preview, index) => (
                  <div key={preview.id} className="relative">
                    <img
                      src={preview.preview}
                      alt={`Preview ${index + 1}`}
                      className="border rounded-md dark:!border-neutral-500 w-full h-24 object-cover"
                    />
                    <button
                      type="button"
                      onClick={() => removeImage(index)}
                      className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-red-600 transition-colors"
                    >
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={2}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="w-4 h-4"
                      >
                        <path d="M3 6h18" />
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                      </svg>
                    </button>
                  </div>
                ))}
              </div>
            )}
            <div className="flex flex-row items-center rounded-lg border dark:!border-neutral-500 bg-gray-50 dark:bg-neutral-700 p-3 shadow-sm">
              <p className="text-sm font-medium flex-1">
                Thêm ảnh vào bài viết của bạn
                {imageFiles.length > 0 && (
                  <span className="ml-2 text-primary-500 font-semibold">
                    ({imageFiles.length} ảnh đã chọn)
                  </span>
                )}
              </p>
              <input
                id="fileInput"
                accept="image/*"
                type="file"
                multiple
                onChange={handleImageChange}
                style={{ display: "none" }}
              />
              <div className="flex gap-1">
                <Button
                  size="small"
                  className="h-8 px-2 rounded-full border-0"
                  onClick={() => document.getElementById("fileInput").click()}
                >
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
              disabled={processing}
              onClick={handleSubmit}
            >
              {processing ? "Đang đăng..." : "Đăng"}
            </CustomColorButton>
          </form>
        </div>
      </Modal>
    </>
  );
};

export default CreatePostModal;
