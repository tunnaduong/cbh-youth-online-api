import { useEffect } from "react";
import InputError from "@/Components/InputError";
import { Head, Link, useForm } from "@inertiajs/react";
import CustomColorButton from "@/Components/ui/CustomColorButton";
import { Input } from "antd";
import { LockOutlined, MailOutlined } from "@ant-design/icons";

export default function ResetPassword({ token, email }) {
  const { data, setData, post, processing, errors, reset } = useForm({
    token: token,
    email: email,
    password: "",
    password_confirmation: "",
  });

  useEffect(() => {
    return () => {
      reset("password", "password_confirmation");
    };
  }, []);

  const submit = (e) => {
    e.preventDefault();
    post(route("password.store"));
  };

  return (
    <>
      <Head title="Đặt lại mật khẩu" />

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
            <div className="my-4 text-sm text-gray-600 dark:text-gray-400 text-center">
              Vui lòng nhập địa chỉ email và mật khẩu mới của bạn để đặt lại mật khẩu.
            </div>

            <form className="space-y-4" onSubmit={submit}>
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
                  placeholder="Mật khẩu mới"
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
                  placeholder="Nhập lại mật khẩu mới"
                  prefix={<LockOutlined />}
                  name="password_confirmation"
                  value={data.password_confirmation}
                  onChange={(e) => setData("password_confirmation", e.target.value)}
                  status={errors.password_confirmation ? "error" : ""}
                />
                <InputError message={errors.password_confirmation} className="mt-2" />
              </div>

              <CustomColorButton
                bgColor={"#319527"}
                block
                className="text-white font-semibold py-[17px] mb-1.5 rounded"
                loading={processing}
                htmlType="submit"
              >
                Đặt lại mật khẩu
              </CustomColorButton>

              <div className="flex justify-center text-sm">
                <Link
                  className="text-primary-500 hover:text-primary-500 hover:underline"
                  href={route("login")}
                >
                  Quay lại đăng nhập
                </Link>
              </div>
            </form>
          </div>
        </div>
      </div>
    </>
  );
}
