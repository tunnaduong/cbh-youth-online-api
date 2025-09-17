import { Button, ConfigProvider } from "antd";
import { TinyColor } from "@ctrl/tinycolor";

function CustomColorButton({ bgColor, children, ...props }) {
  return (
    <ConfigProvider
      theme={{
        components: {
          Button: {
            colorPrimary: bgColor,
            colorPrimaryHover: new TinyColor(bgColor).lighten(5).toString(),
            colorPrimaryActive: new TinyColor(bgColor).darken(5).toString(),
            lineWidth: 0, // Optional: remove border
            borderRadius: 10,
          },
        },
      }}
    >
      <Button type="primary" {...props}>
        {children}
      </Button>
    </ConfigProvider>
  );
}

export default CustomColorButton;
