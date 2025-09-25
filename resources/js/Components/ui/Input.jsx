import { Input as AntdInput } from "antd";

const Input = ({ className = "", ...props }) => {
  return (
    <AntdInput
      {...props}
      prefix={<></>}
      className={`!pl-2 shadow-sm focus:shadow-md-ring ${className}`}
    />
  );
};

Input.TextArea = ({ className = "", shadow = true, ...props }) => {
  return (
    <AntdInput.TextArea
      {...props}
      className={`${
        shadow ? "shadow-sm focus:shadow-md-ring" : ""
      } focus:!bg-transparent ${className}`}
    />
  );
};

export default Input;
