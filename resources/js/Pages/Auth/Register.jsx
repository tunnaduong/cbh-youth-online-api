import { useEffect } from "react";
import InputError from "@/Components/InputError";
import { Head, Link, useForm } from "@inertiajs/react";
import CustomColorButton from "@/Components/ui/CustomColorButton";
import { Input, message, Checkbox } from "antd";
import { LockOutlined, UserOutlined, MailOutlined, IdcardOutlined } from "@ant-design/icons";
import ReCAPTCHA from "react-google-recaptcha";

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({
    username: "",
    profile_name: "",
    email: "",
    password: "",
    password_confirmation: "",
    recaptcha: "",
    agree_terms: "",
  });

  useEffect(() => {
    return () => {
      reset("password", "password_confirmation");
    };
  }, []);

  const submit = (e) => {
    e.preventDefault();
    post(route("register"));
  };

  const onRecaptchaChange = (value) => {
    setData("recaptcha", value);
  };

  return (
    <>
      <Head title="Đăng ký" />

      <div className="min-h-screen flex items-center justify-center auth-background bg-[#eaf3ef] dark:bg-neutral-800 px-4">
        <div className="rounded-xl border bg-card text-card-foreground shadow w-full bg-white dark:!bg-neutral-700 dark:!border-neutral-500 max-w-md">
          <div className="flex flex-col p-6 -mb-5 space-y-4 text-center">
            <div className="flex justify-center">
              <Link className="flex gap-x-1 items-center" href="/">
                <img alt="CYO's Logo" width={50} src="/images/logo.png" />
                <div className="text-[18px] text-left font-light text-[#319527] leading-5">
                  <h1 className="font-light">Diễn đàn học sinh</h1>
                  <h1 className="font-bold">Chuyên Biên Hòa</h1>
                </div>
              </Link>
            </div>
          </div>
          <div className="p-6 pt-0">
            <form className="space-y-4" onSubmit={submit}>
              <input type="hidden" name="_token" defaultValue="" />
              <div className="space-y-2">
                <Input
                  placeholder="Tên đăng nhập"
                  prefix={<UserOutlined />}
                  name="username"
                  value={data.username}
                  onChange={(e) => setData("username", e.target.value)}
                  status={errors.username ? "error" : ""}
                />
                <InputError message={errors.username} className="mt-2" />
              </div>

              <div className="space-y-2">
                <Input
                  placeholder="Họ và tên"
                  prefix={<IdcardOutlined />}
                  name="profile_name"
                  value={data.profile_name}
                  onChange={(e) => setData("profile_name", e.target.value)}
                  status={errors.profile_name ? "error" : ""}
                />
                <InputError message={errors.profile_name} className="mt-2" />
              </div>

              <div className="space-y-2">
                <Input
                  placeholder="Địa chỉ email"
                  prefix={<MailOutlined />}
                  name="email"
                  type="email"
                  value={data.email}
                  onChange={(e) => setData("email", e.target.value)}
                  status={errors.email ? "error" : ""}
                />
                <InputError message={errors.email} className="mt-2" />
              </div>

              <div className="space-y-2">
                <Input
                  type="password"
                  placeholder="Mật khẩu"
                  prefix={<LockOutlined />}
                  name="password"
                  value={data.password}
                  onChange={(e) => setData("password", e.target.value)}
                  status={errors.password ? "error" : ""}
                />
                <InputError message={errors.password} className="mt-2" />
              </div>

              <div className="space-y-2">
                <Input
                  type="password"
                  placeholder="Nhập lại mật khẩu"
                  prefix={<LockOutlined />}
                  name="password_confirmation"
                  value={data.password_confirmation}
                  onChange={(e) => setData("password_confirmation", e.target.value)}
                  status={errors.password_confirmation ? "error" : ""}
                />
                <InputError message={errors.password_confirmation} className="mt-2" />
              </div>

              <div className="mt-3">
                <label className="flex items-start">
                  <Checkbox
                    name="agree_terms"
                    checked={data.agree_terms}
                    onChange={(e) => setData("agree_terms", e.target.checked)}
                    required
                  />
                  <span className="text-sm ml-2 dark:text-white">
                    Tôi đã đọc và đồng ý với{" "}
                    <Link
                      href={route("policy.terms")}
                      className="!text-primary-500 hover:underline"
                    >
                      Điều khoản sử dụng
                    </Link>{" "}
                    và{" "}
                    <Link
                      href={route("policy.privacy")}
                      className="!text-primary-500 hover:underline"
                    >
                      Chính sách quyền riêng tư
                    </Link>
                  </span>
                </label>
                <InputError message={errors.agree_terms} />
              </div>

              <div className="flex justify-center">
                <ReCAPTCHA
                  sitekey={import.meta.env.VITE_RECAPTCHA_SITE_KEY}
                  onChange={onRecaptchaChange}
                />
              </div>
              <InputError message={errors.recaptcha} className="mt-2" />

              <CustomColorButton
                bgColor={"#319527"}
                block
                className="text-white font-semibold py-[17px] mb-1.5 rounded"
                loading={processing}
                htmlType="submit"
              >
                Đăng ký
              </CustomColorButton>

              <div className="flex justify-center text-sm">
                <Link
                  className="text-primary-500 hover:text-primary-500 hover:underline"
                  href={route("login")}
                >
                  Đã có tài khoản? Đăng nhập
                </Link>
              </div>
            </form>
          </div>
          <div className="flex items-center p-6 pt-0">
            <div className="w-full space-y-2">
              <div className="flex justify-center space-x-4">
                <a
                  href={route("social.redirect", "facebook")}
                  className="inline-flex dark:!border-neutral-500 dark:bg-[#2c2c2c] items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 border border-input shadow-sm hover:bg-[#eeeeee] hover:text-accent-foreground w-10 h-10"
                >
                  <svg
                    stroke="currentColor"
                    fill="currentColor"
                    strokeWidth={0}
                    viewBox="0 0 512 512"
                    className="w-5 h-5 text-blue-600"
                    height="1em"
                    width="1em"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path
                      fillRule="evenodd"
                      d="M480 257.35c0-123.7-100.3-224-224-224s-224 100.3-224 224c0 111.8 81.9 204.47 189 221.29V322.12h-56.89v-64.77H221V208c0-56.13 33.45-87.16 84.61-87.16 24.51 0 50.15 4.38 50.15 4.38v55.13H327.5c-27.81 0-36.51 17.26-36.51 35v42h62.12l-9.92 64.77H291v156.54c107.1-16.81 189-109.48 189-221.31z"
                    ></path>
                  </svg>
                  <span className="sr-only">Facebook</span>
                </a>
                <a
                  href={route("social.redirect", "google")}
                  className="inline-flex dark:!border-neutral-500 dark:bg-[#2c2c2c] items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0 border border-input shadow-sm hover:bg-[#eeeeee] hover:text-accent-foreground w-10 h-10"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" className="w-5 h-5">
                    <path
                      fill="#FFC107"
                      d="M43.611,20.083H42V20H24v8h11.303c-1.649,4.657-6.08,8-11.303,8c-6.627,0-12-5.373-12-12c0-6.627,5.373-12,12-12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C12.955,4,4,12.955,4,24c0,11.045,8.955,20,20,20c11.045,0,20-8.955,20-20C44,22.659,43.862,21.35,43.611,20.083z"
                    ></path>
                    <path
                      fill="#FF3D00"
                      d="M6.306,14.691l6.571,4.819C14.655,15.108,18.961,12,24,12c3.059,0,5.842,1.154,7.961,3.039l5.657-5.657C34.046,6.053,29.268,4,24,4C16.318,4,9.656,8.337,6.306,14.691z"
                    ></path>
                    <path
                      fill="#4CAF50"
                      d="M24,44c5.166,0,9.86-1.977,13.409-5.192l-6.19-5.238C29.211,35.091,26.715,36,24,36c-5.202,0-9.619-3.317-11.283-7.946l-6.522,5.025C9.505,39.556,16.227,44,24,44z"
                    ></path>
                    <path
                      fill="#1976D2"
                      d="M43.611,20.083H42V20H24v8h11.303c-0.792,2.237-2.231,4.166-4.087,5.571c0.001-0.001,0.002-0.001,0.003-0.002l6.19,5.238C36.971,39.205,44,34,44,24C44,22.659,43.862,21.35,43.611,20.083z"
                    ></path>
                  </svg>
                  <span className="sr-only">Google</span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </>
  );
}
