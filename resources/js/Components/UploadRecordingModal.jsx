import { Modal, Button } from "antd";
import { IoMusicalNotes } from "react-icons/io5";
import { useState } from "react";

export default function UploadRecordingModal({ open, onClose }) {
  const [file, setFile] = useState(null);

  return (
    <Modal open={open} onCancel={onClose} footer={null} centered>
      <div className="flex flex-row justify-center items-center pb-[34px] relative">
        <h1 className="text-lg font-bold text-center absolute -top-1.5">Đăng ghi âm</h1>
      </div>
      <hr className="absolute right-0 left-0 w-full" />
      <div className="pt-4 flex flex-col gap-4">
        <div className="text-[15px] text-center">
          Định dạng tệp được cho phép: <b className="font-semibold">.mp3 .m4a .wav .ogg</b>
        </div>

        <div className="border-2 border-dashed border-gray-300 rounded-2xl p-4 flex flex-col items-center justify-center">
          <div className="relative">
            <img src="/images/audio_file.svg" alt="Audio file" className="w-16 h-16 mb-4" />
            <div className="absolute bottom-0 right-0 mb-3">
              <img src="/images/upload.png" alt="Upload" className="w-[26px] h-[26px]" />
            </div>
          </div>
          <div className="text-[15px] text-center">
            <span className="text-black">
              <b className="font-semibold">Click để tải lên</b> hoặc kéo và thả
            </span>
            <p className="text-gray-500 text-xs">Kích cỡ tệp tin tối đa 50MB.</p>
          </div>
        </div>

        <div className="flex items-start gap-2 border border-gray-300 rounded-xl p-3">
          <div className="bg-[#F4F4F4] border-2 border-[#EAEAEA] rounded-xl w-10 h-10 flex items-center justify-center">
            <IoMusicalNotes size={24} className="text-[#BBBBBB]" />
          </div>
          <div className="flex-1">
            <b className="text-gray-700 font-semibold">Thong_bao_Doan_30-11-2023.mp3</b>
            <p className="text-gray-500 text-xs">20MB</p>
            <div className="flex gap-2 items-center">
              <div className="bg-gray-300 flex-1 h-1.5 rounded-full">
                <div style={{ width: "73%" }} className="bg-black h-1.5 rounded-full"></div>
              </div>
              <span className="text-xs">73%</span>
            </div>
          </div>
        </div>

        <div className="flex gap-2">
          <Button block className="rounded-lg">
            Tự ghi âm
          </Button>
          <Button type="primary" block className="rounded-lg">
            Đăng lên
          </Button>
        </div>
      </div>
    </Modal>
  );
}
