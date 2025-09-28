import { Head, Link, useForm } from "@inertiajs/react";
import DefaultLayout from "@/Layouts/DefaultLayout";
import { useState } from "react";
import { Button, DatePicker, Radio, Select, Switch } from "antd";
import { Edit2Icon, User, Bell, Shield, Trash2 } from "lucide-react";
import Input from "@/Components/ui/Input";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import dayjs from "dayjs";

export default function Settings({ auth, user }) {
  const [activeTab, setActiveTab] = useState("profile");

  console.log(user);

  const {
    data,
    setData,
    post,
    delete: deleteAccount,
    processing,
    errors,
  } = useForm({
    // Profile settings
    username: user?.username || "",
    email: user?.email || "",
    gender: user?.profile?.gender || "male",
    location: user?.profile?.location || "",
    bio: user?.profile?.bio || "",
    // Account settings
    full_name: user?.profile?.profile_name || "",
    date_of_birth: user?.profile?.birthday ? dayjs(user.profile.birthday) : null,
    language: "vi",
    // Notification settings
    notification_level: "all",
    email_contact: true,
    email_marketing: true,
    email_social: true,
    email_security: true,
    // Delete account
    password: "",
    confirm_text: "",
  });

  const handleSubmitProfile = (e) => {
    e.preventDefault();
    post(route("settings.update"));
  };

  const handleSubmitAccount = (e) => {
    e.preventDefault();
    post(route("settings.update"));
  };

  const handleSubmitNotifications = (e) => {
    e.preventDefault();
    post(route("settings.update"));
  };

  const handleSubmitDelete = (e) => {
    e.preventDefault();

    console.log("Form submitted:", {
      activeTab,
      confirm_text: data.confirm_text,
      password: data.password ? "***" : "empty",
    });

    // Check if this is account deletion form
    if (data.confirm_text === "XÓA TÀI KHOẢN") {
      console.log("Attempting account deletion...");
      // Handle account deletion
      deleteAccount(route("settings.delete-account"));
    } else {
      console.log("Updating settings...");
      // Handle other settings updates
      post(route("settings.update"));
    }
  };

  const renderTabContent = () => {
    switch (activeTab) {
      case "profile":
        return (
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                Trang cá nhân
              </h3>
              <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Đây là cách người khác sẽ nhìn thấy bạn trên trang web.
              </p>
            </div>

            <form
              onSubmit={handleSubmitProfile}
              className="space-y-6 md:space-y-0 flex flex-col-reverse md:flex-row gap-4"
            >
              {/* Username */}
              <div className="flex flex-col gap-y-4">
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tên đăng nhập
                  </label>
                  <Input
                    type="text"
                    value={data.username}
                    onChange={(e) => setData("username", e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="Nhập tên đăng nhập"
                  />

                  <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Đây là tên hiển thị công khai của bạn. Có thể là tên thật hoặc biệt danh. Chỉ có
                    thể thay đổi mỗi 30 ngày.
                  </p>
                  {errors.username && (
                    <p className="mt-1 text-xs text-red-500">{errors.username}</p>
                  )}
                </div>

                {/* Email */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Email đăng ký
                  </label>
                  <Input
                    type="email"
                    value={data.email}
                    onChange={(e) => setData("email", e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 "
                    placeholder="Email đăng ký"
                  />
                </div>

                {/* Gender */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Giới tính
                  </label>
                  <Radio.Group
                    value={data.gender}
                    options={[
                      { value: "male", label: "Nam" },
                      { value: "female", label: "Nữ" },
                    ]}
                    onChange={(e) => setData("gender", e.target.value)}
                    className="text-gray-700 dark:text-gray-300"
                  />
                </div>

                {/* Location */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Quê quán
                  </label>
                  <Input
                    type="text"
                    value={data.location}
                    onChange={(e) => setData("location", e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="Nhập quê quán"
                  />
                </div>

                {/* Bio */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Tiểu sử
                  </label>
                  <Input.TextArea
                    value={data.bio}
                    onChange={(e) => setData("bio", e.target.value)}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                    placeholder="Nhập tiểu sử của bạn"
                    rows={4}
                  />
                </div>

                {/* Submit Button */}
                <div className="pt-2">
                  <Button
                    type="submit"
                    loading={processing}
                    className="bg-primary-500 hover:bg-green-600 text-white border-0 px-6 py-2 rounded-md font-medium"
                  >
                    Cập nhật hồ sơ
                  </Button>
                </div>
              </div>
              {/* Profile Picture */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Ảnh đại diện
                </label>
                <div className="flex items-center space-x-4">
                  <div className="relative">
                    <img
                      src={`https://api.chuyenbienhoa.com/v1.0/users/${user?.username}/avatar`}
                      alt="Avatar"
                      className="border border-gray-300 dark:!border-[#737373] w-52 h-52 rounded-full object-cover"
                    />
                    <Button className="flex items-center dark:border-[#737373] absolute left-0 bottom-0 ml-2 mb-2 h-[36px] !px-3 text-[13px] font-semibold">
                      <Edit2Icon className="w-4 h-4" />
                      <span>Sửa</span>
                    </Button>
                  </div>
                </div>
              </div>
            </form>
          </div>
        );

      case "account":
        return (
          <div className="space-y-6">
            <div>
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                Tài khoản
              </h3>
              <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                Cập nhật cài đặt tài khoản của bạn. Đặt ngôn ngữ và múi giờ ưa thích của bạn.
              </p>
            </div>

            <form onSubmit={handleSubmitAccount} className="space-y-6">
              {/* Full Name */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Họ và tên
                </label>
                <Input
                  type="text"
                  value={data.full_name}
                  onChange={(e) => setData("full_name", e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent"
                  placeholder="Dương Tùng Anh (Tunna Duong)"
                />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Đây là tên sẽ được hiển thị công khai trên hồ sơ của bạn, bảng tin và diễn đàn.
                </p>
                {errors.full_name && (
                  <p className="mt-1 text-xs text-red-500">{errors.full_name}</p>
                )}
              </div>

              {/* Date of Birth */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Ngày tháng năm sinh
                </label>
                <div className="relative">
                  <DatePicker
                    value={data.date_of_birth}
                    onChange={(date) => setData("date_of_birth", date)}
                    placeholder="Chọn ngày sinh"
                    format="DD/MM/YYYY"
                    className="w-full"
                  />
                </div>
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Ngày sinh của bạn được sử dụng để tính toán tuổi của bạn.
                </p>
                {errors.date_of_birth && (
                  <p className="mt-1 text-xs text-red-500">{errors.date_of_birth}</p>
                )}
              </div>

              {/* Language */}
              <div>
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Ngôn ngữ
                </label>

                <Select
                  value={data.language}
                  onChange={(value) => setData("language", value)}
                  style={{ width: "100%" }}
                  options={[{ label: "Tiếng Việt", value: "vi" }]}
                  placeholder="Chọn ngôn ngữ"
                  className="shadow-sm"
                />
                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Đây là ngôn ngữ sẽ được sử dụng trên website
                </p>
                {errors.language && <p className="mt-1 text-xs text-red-500">{errors.language}</p>}
              </div>

              {/* Submit Button */}
              <div>
                <Button
                  type="submit"
                  loading={processing}
                  className="bg-green-600 hover:bg-green-700 text-white border-0 px-6 py-2 rounded-md font-medium"
                >
                  Cập nhật hồ sơ
                </Button>
              </div>
            </form>

            {/* Delete Account Section */}
            <div className="mt-12 pt-8 border-t border-gray-200 dark:border-gray-700">
              <div className="space-y-6">
                <div>
                  <h3 className="text-lg font-semibold text-red-600 dark:text-red-400 mb-2">
                    Xóa tài khoản
                  </h3>
                  <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    Xóa vĩnh viễn tài khoản và tất cả dữ liệu của bạn. Hành động này không thể hoàn
                    tác.
                  </p>
                </div>

                <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <Trash2 className="h-5 w-5 text-red-400" />
                    </div>
                    <div className="ml-3">
                      <h3 className="text-sm font-medium text-red-800 dark:text-red-200">
                        Cảnh báo quan trọng
                      </h3>
                      <div className="mt-2 text-sm text-red-700 dark:text-red-300">
                        <ul className="list-disc list-inside space-y-1">
                          <li>
                            Tất cả bài viết, bình luận và tương tác của bạn sẽ bị xóa vĩnh viễn
                          </li>
                          <li>Bạn sẽ mất quyền truy cập vào tài khoản này</li>
                          <li>Không thể khôi phục dữ liệu sau khi xóa</li>
                          <li>Email của bạn có thể được sử dụng để tạo tài khoản mới</li>
                        </ul>
                      </div>
                    </div>
                  </div>
                </div>

                <form onSubmit={handleSubmitDelete} className="space-y-6">
                  {/* Password Confirmation */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Mật khẩu hiện tại
                    </label>
                    <input
                      type="password"
                      value={data.password}
                      onChange={(e) => setData("password", e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                      placeholder="Nhập mật khẩu hiện tại"
                      required
                    />
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                      Chúng tôi cần xác nhận mật khẩu để xóa tài khoản
                    </p>
                    {errors.password && (
                      <p className="mt-1 text-xs text-red-500">{errors.password}</p>
                    )}
                  </div>

                  {/* Confirmation Text */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                      Xác nhận xóa tài khoản
                    </label>
                    <input
                      type="text"
                      value={data.confirm_text}
                      onChange={(e) => setData("confirm_text", e.target.value)}
                      className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                      placeholder="Gõ 'XÓA TÀI KHOẢN' để xác nhận"
                      required
                    />
                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                      Gõ chính xác "XÓA TÀI KHOẢN" (viết hoa) để xác nhận
                    </p>
                    {errors.confirm_text && (
                      <p className="mt-1 text-xs text-red-500">{errors.confirm_text}</p>
                    )}
                  </div>

                  {/* Submit Button */}
                  <div>
                    <Button
                      type="submit"
                      loading={processing}
                      disabled={data.confirm_text !== "XÓA TÀI KHOẢN"}
                      className="bg-red-600 hover:bg-red-700 text-white border-0 px-6 py-2 rounded-md font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                      htmlType="submit"
                    >
                      Xóa vĩnh viễn tài khoản
                    </Button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        );

      case "notifications":
        return (
          <div className="space-y-8">
            <form onSubmit={handleSubmitNotifications} className="space-y-8">
              {/* General Notifications */}
              <div>
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                  Thông báo
                </h3>
                <p className="text-sm text-gray-600 dark:text-gray-400 mb-6">
                  Cấu hình cách bạn nhận thông báo.
                </p>

                <Radio.Group
                  value={data.notification_level}
                  onChange={(e) => setData("notification_level", e.target.value)}
                  className="space-y-3"
                >
                  <div className="flex flex-col space-y-3">
                    <Radio value="all" className="text-gray-700 dark:text-gray-300">
                      Tất cả thông báo
                    </Radio>
                    <Radio value="mentions" className="text-gray-700 dark:text-gray-300">
                      Tin nhắn trực tiếp và đề cập
                    </Radio>
                    <Radio value="none" className="text-gray-700 dark:text-gray-300">
                      Không có thông báo
                    </Radio>
                  </div>
                </Radio.Group>
              </div>

              {/* Email Notifications */}
              <div>
                <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                  Thông báo qua email
                </h3>

                <div className="space-y-6">
                  {/* Contact Email */}
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <h4 className="font-medium text-gray-900 dark:text-white">Email liên lạc</h4>
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        Nhận email về hoạt động tài khoản của bạn.
                      </p>
                    </div>
                    <Switch
                      checked={data.email_contact}
                      onChange={(checked) => setData("email_contact", checked)}
                      className="ml-4"
                    />
                  </div>

                  {/* Marketing Email */}
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <h4 className="font-medium text-gray-900 dark:text-white">Email marketing</h4>
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        Nhận email về sản phẩm mới, tính năng, và nhiều hơn nữa.
                      </p>
                    </div>
                    <Switch
                      checked={data.email_marketing}
                      onChange={(checked) => setData("email_marketing", checked)}
                      className="ml-4"
                    />
                  </div>

                  {/* Social Email */}
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <h4 className="font-medium text-gray-900 dark:text-white">Email xã hội</h4>
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        Nhận email về yêu cầu kết bạn, theo dõi, và nhiều hơn nữa.
                      </p>
                    </div>
                    <Switch
                      checked={data.email_social}
                      onChange={(checked) => setData("email_social", checked)}
                      className="ml-4"
                    />
                  </div>

                  {/* Security Email */}
                  <div className="flex items-center justify-between">
                    <div className="flex-1">
                      <h4 className="font-medium text-gray-900 dark:text-white">Email bảo mật</h4>
                      <p className="text-sm text-gray-600 dark:text-gray-400">
                        Nhận email về hoạt động và bảo mật tài khoản của bạn.
                      </p>
                    </div>
                    <Switch
                      checked={data.email_security}
                      onChange={(checked) => setData("email_security", checked)}
                      className="ml-4"
                    />
                  </div>
                </div>
              </div>

              {/* Submit Button */}
              <div>
                <Button
                  type="submit"
                  loading={processing}
                  className="bg-green-600 hover:bg-green-700 text-white border-0 px-6 py-2 rounded-md font-medium"
                >
                  Cập nhật thông báo
                </Button>
              </div>
            </form>
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <AuthenticatedLayout>
      <Head title="Cài đặt" />

      <div className="min-h-screen">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Header */}
          <div className="mb-8">
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Cài đặt</h1>
            <p className="mt-2 text-gray-600 dark:text-gray-400">
              Quản lý cài đặt tài khoản và thiết lập thông báo email.
            </p>
          </div>

          <div className="flex flex-col lg:flex-row gap-8">
            {/* Sidebar */}
            <div className="lg:w-64 flex-shrink-0">
              <nav className="space-y-1">
                <button
                  onClick={() => setActiveTab("profile")}
                  className={`w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                    activeTab === "profile"
                      ? "bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border-r-2 border-green-600 dark:border-green-400"
                      : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-neutral-800"
                  }`}
                >
                  <User className="w-5 h-5 mr-3" />
                  Trang cá nhân
                </button>
                <button
                  onClick={() => setActiveTab("account")}
                  className={`w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                    activeTab === "account"
                      ? "bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border-r-2 border-green-600 dark:border-green-400"
                      : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-neutral-800"
                  }`}
                >
                  <Shield className="w-5 h-5 mr-3" />
                  Tài khoản
                </button>
                <button
                  onClick={() => setActiveTab("notifications")}
                  className={`w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors ${
                    activeTab === "notifications"
                      ? "bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 border-r-2 border-green-600 dark:border-green-400"
                      : "text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-50 dark:hover:bg-neutral-800"
                  }`}
                >
                  <Bell className="w-5 h-5 mr-3" />
                  Thông báo
                </button>
              </nav>
            </div>

            {/* Main Content */}
            <div className="flex-1">
              <div className="bg-white dark:!bg-[#3c3c3c] rounded-lg shadow-sm border border-gray-200 dark:!border-[#737373] p-6">
                {renderTabContent()}
              </div>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
