import React, { useState } from "react";
import { Modal, Upload, Button, Select, message, ColorPicker, Checkbox } from "antd";
import { CameraOutlined, VideoCameraOutlined, FileTextOutlined } from "@ant-design/icons";
import { usePage, useForm, router } from "@inertiajs/react";
import Input from "./ui/Input";
import { PiFileAudio } from "react-icons/pi";

const gradientBackgrounds = [
  ["#FF6B6B", "#4ECDC4"],
  ["#A8E6CF", "#DCEDC1"],
  ["#FFD93D", "#FF6B6B"],
  ["#6C5B7B", "#C06C84"],
  ["#355C7D", "#6C5B7B"],
];

const CreateStoryModal = ({ open, onClose, onStoryCreated }) => {
  const { auth } = usePage().props;
  const [mediaType, setMediaType] = useState("text");
  const [mediaFile, setMediaFile] = useState(null);
  const [previewUrl, setPreviewUrl] = useState(null);
  const [useCustomColor, setUseCustomColor] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const { data, setData, post, processing, errors, reset } = useForm({
    content: "",
    media_type: "text",
    media_file: null,
    background_color: ["#FF6B6B", "#4ECDC4"],
    font_style: "normal",
    text_position: { x: 50, y: 50 },
    privacy: "public",
    duration: 5,
  });

  const handleSubmit = (e) => {
    e.preventDefault();

    if (!auth?.user) {
      message.error("Bạn cần đăng nhập để tạo tin");
      return;
    }

    if (mediaType === "text" && !data.content.trim()) {
      message.error("Vui lòng nhập nội dung tin");
      return;
    }

    if (mediaType !== "text" && !mediaFile) {
      message.error("Vui lòng chọn file media");
      return;
    }

    // Create FormData for file upload
    const formData = new FormData();
    formData.append("content", data.content || "");
    formData.append("media_type", mediaType);
    formData.append("privacy", data.privacy);
    formData.append("duration", data.duration);

    // Add media file if present
    if (mediaFile) {
      formData.append("media_file", mediaFile);
    }

    // Only add text-specific fields for text stories
    if (mediaType === "text") {
      formData.append(
        "background_color",
        Array.isArray(data.background_color)
          ? JSON.stringify(data.background_color)
          : data.background_color
      );
      formData.append("font_style", data.font_style);
      formData.append("text_position", JSON.stringify(data.text_position));
    }

    // Debug: Log the data being sent
    console.log("Sending data:", {
      content: data.content,
      media_type: mediaType,
      media_file: mediaFile,
      background_color: mediaType === "text" ? data.background_color : undefined,
      text_position: mediaType === "text" ? data.text_position : undefined,
      privacy: data.privacy,
      duration: data.duration,
    });

    // Set loading state
    setIsSubmitting(true);

    // Use router.post with FormData
    router.post(route("api.stories.store"), formData, {
      showProgress: false,
      onSuccess: (page) => {
        message.success("Tin đã được tạo thành công!");
        reset();
        setMediaFile(null);
        setPreviewUrl(null);
        setUseCustomColor(false);
        setIsSubmitting(false);
        onClose();

        // Call the callback to update stories list
        if (onStoryCreated) {
          onStoryCreated();
        }
      },
      onError: (errors) => {
        console.error("Form errors:", errors);
        setIsSubmitting(false);
        if (errors.media_file) {
          message.error(`Lỗi file: ${errors.media_file}`);
        } else if (errors.content) {
          message.error(`Lỗi nội dung: ${errors.content}`);
        } else {
          message.error("Có lỗi xảy ra khi tạo tin. Vui lòng thử lại.");
        }
      },
    });
  };

  const handleMediaChange = (info) => {
    if (info.file) {
      setMediaFile(info.file);
      const reader = new FileReader();
      reader.onload = (e) => setPreviewUrl(e.target.result);
      reader.readAsDataURL(info.file);
    }
  };

  const handleMediaTypeChange = (type) => {
    setMediaType(type);
    setData("media_type", type);
    setMediaFile(null);
    setPreviewUrl(null);
  };

  const getMediaIcon = () => {
    switch (mediaType) {
      case "image":
        return <CameraOutlined />;
      case "video":
        return <VideoCameraOutlined />;
      case "audio":
        return <PiFileAudio />;
      default:
        return <FileTextOutlined />;
    }
  };

  return (
    <Modal open={open} onCancel={onClose} footer={null} width={600} style={{ top: 40 }}>
      <div className="flex flex-row justify-center items-center pb-[34px] relative">
        <h1 className="text-lg font-bold text-center absolute -top-1.5">Tạo tin mới</h1>
      </div>
      <hr className="absolute right-0 left-0 w-full" />
      <form onSubmit={handleSubmit} className="space-y-4 mt-4">
        {/* Media Type Selection */}
        <div>
          <label className="block text-sm font-medium mb-2">Loại tin</label>
          <Select
            value={mediaType}
            onChange={handleMediaTypeChange}
            style={{ width: "100%" }}
            options={[
              { label: "Tin văn bản", value: "text" },
              { label: "Tin hình ảnh", value: "image" },
              { label: "Tin video", value: "video" },
              { label: "Tin âm thanh", value: "audio" },
            ]}
          />
        </div>

        {/* Content Input */}
        {mediaType === "text" && (
          <div>
            <label className="block text-sm font-medium mb-2">Nội dung</label>
            <Input.TextArea
              name="description"
              value={data.content}
              onChange={(e) => setData("content", e.target.value)}
              placeholder="Nhập nội dung tin..."
              rows={4}
            />
            {errors.content && <div className="text-red-500 text-sm mt-1">{errors.content}</div>}
          </div>
        )}

        {/* Media Upload */}
        {mediaType !== "text" && (
          <div>
            <label className="block text-sm font-medium mb-2">File media</label>
            <Upload
              beforeUpload={() => false}
              onChange={handleMediaChange}
              showUploadList={false}
              accept={
                mediaType === "image" ? "image/*" : mediaType === "video" ? "video/*" : "audio/*"
              }
            >
              <Button icon={getMediaIcon()}>
                Chọn{" "}
                {mediaType === "image" ? "hình ảnh" : mediaType === "video" ? "video" : "âm thanh"}
              </Button>
            </Upload>
            {previewUrl && (
              <div className="mt-2">
                {mediaType === "image" ? (
                  <img
                    src={previewUrl}
                    alt="Preview"
                    className="max-w-full h-32 object-cover rounded"
                  />
                ) : (
                  <div className="p-4 bg-gray-100 rounded text-center">
                    <p className="text-sm text-gray-600">File đã chọn: {mediaFile?.name}</p>
                  </div>
                )}
              </div>
            )}
          </div>
        )}

        {/* Background Color (for text stories) */}
        {mediaType === "text" && (
          <div>
            <label className="block text-sm font-medium mb-2">Màu nền</label>

            {/* Predefined Gradients */}
            <div className="mb-2">
              <div className="grid grid-cols-5 gap-2">
                {gradientBackgrounds.map((gradient, index) => (
                  <div
                    key={index}
                    className="w-full h-12 rounded-lg cursor-pointer border-2 hover:border-primary-500 transition-colors"
                    style={{
                      background: `linear-gradient(135deg, ${gradient[0]}, ${gradient[1]})`,
                      borderColor:
                        JSON.stringify(data.background_color) === JSON.stringify(gradient)
                          ? "#319527"
                          : "#e5e7eb",
                    }}
                    onClick={() => {
                      setData("background_color", gradient);
                      setUseCustomColor(false);
                    }}
                  />
                ))}
              </div>
            </div>

            {/* Custom Color Option */}
            <div className="flex items-center gap-2 mb-2">
              <Checkbox
                id="customColor"
                checked={useCustomColor}
                onChange={(e) => setUseCustomColor(e.target.checked)}
              >
                <span className="text-sm text-gray-600 dark:text-white">Tùy chỉnh màu</span>
              </Checkbox>
            </div>

            {/* Custom Color Pickers */}
            {useCustomColor && (
              <div className="flex gap-2 mt-2">
                <ColorPicker
                  value={
                    Array.isArray(data.background_color)
                      ? data.background_color[0]
                      : data.background_color
                  }
                  onChange={(color) => {
                    const newColor = color.toHexString();
                    if (Array.isArray(data.background_color)) {
                      setData("background_color", [newColor, data.background_color[1]]);
                    } else {
                      setData("background_color", [newColor, "#6C5B7B"]);
                    }
                  }}
                  className="!bg-white dark:!bg-[#3c3c3c]"
                />
                <ColorPicker
                  value={
                    Array.isArray(data.background_color) ? data.background_color[1] : "#6C5B7B"
                  }
                  onChange={(color) => {
                    const newColor = color.toHexString();
                    if (Array.isArray(data.background_color)) {
                      setData("background_color", [data.background_color[0], newColor]);
                    } else {
                      setData("background_color", [data.background_color, newColor]);
                    }
                  }}
                  className="!bg-white dark:!bg-[#3c3c3c]"
                />
              </div>
            )}
          </div>
        )}

        {/* Privacy Setting */}
        <div>
          <label className="block text-sm font-medium mb-2">Quyền riêng tư</label>
          <Select
            value={data.privacy}
            onChange={(value) => setData("privacy", value)}
            style={{ width: "100%" }}
            options={[
              { label: "Công khai", value: "public" },
              { label: "Chỉ người theo dõi", value: "followers" },
            ]}
          />
        </div>
        {/* Duration Setting */}
        {mediaType !== "video" && mediaType !== "audio" && (
          <div>
            <label className="block text-sm font-medium mb-2">Thời gian hiển thị (giây)</label>
            <Input
              type="number"
              value={data.duration}
              onChange={(e) => setData("duration", parseInt(e.target.value))}
              min={1}
              max={30}
              className="shadow-none"
            />
          </div>
        )}

        {/* Preview */}
        {mediaType === "text" && data.content && (
          <div>
            <label className="block text-sm font-medium mb-2">Xem trước</label>
            <div
              className="w-full h-32 rounded-lg flex items-center justify-center text-white font-semibold"
              style={{
                background: Array.isArray(data.background_color)
                  ? `linear-gradient(135deg, ${data.background_color[0]}, ${data.background_color[1]})`
                  : data.background_color,
              }}
            >
              {data.content}
            </div>
          </div>
        )}

        {/* Submit Button */}
        <div className="flex justify-end space-x-2 pt-4">
          <Button onClick={onClose} disabled={isSubmitting}>
            Hủy
          </Button>
          <Button type="primary" htmlType="submit" loading={isSubmitting}>
            Tạo tin
          </Button>
        </div>
      </form>
    </Modal>
  );
};

export default CreateStoryModal;
