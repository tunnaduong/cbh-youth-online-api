import InputError from "@/Components/InputError";
import { Head, Link, useForm } from "@inertiajs/react";
import CustomColorButton from "@/Components/ui/CustomColorButton";
import { Input } from "antd";
import { MailOutlined } from "@ant-design/icons";

export default function ForgotPassword({ status }) {
  const { data, setData, post, processing, errors } = useForm({
    email: "",
  });

  const submit = (e) => {
    e.preventDefault();
    post(route("password.email"));
  };

  return (
    <>
      <Head title="Quên mật khẩu" />

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
              Quên mật khẩu? Không sao cả. Chỉ cần cho chúng tôi biết địa chỉ email của bạn và chúng
              tôi sẽ gửi cho bạn một liên kết đặt lại mật khẩu để chọn mật khẩu mới.
            </div>

            {status && (
              <div className="mb-4 font-medium text-sm text-green-600 dark:text-green-400 text-center">
                {status}
              </div>
            )}

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

              <CustomColorButton
                bgColor={"#319527"}
                block
                className="text-white font-semibold py-[17px] mb-1.5 rounded"
                loading={processing}
                htmlType="submit"
              >
                Gửi liên kết đặt lại mật khẩu
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
