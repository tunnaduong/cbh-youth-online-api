import { Input as AntdInput } from "antd";

const Input = ({ ...props }) => {
  return <AntdInput {...props} prefix={<></>} className="!pl-2 shadow-sm focus:shadow-md-ring" />;
};

Input.TextArea = ({ ...props }) => {
  return <AntdInput.TextArea {...props} className="shadow-sm focus:shadow-md-ring" />;
};

export default Input;
