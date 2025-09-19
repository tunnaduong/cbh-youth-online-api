import moment from "moment";
import "moment/locale/vi";

// Configure Vietnamese locale for moment.js
export const configureVietnameseLocale = () => {
  moment.locale("vi", {
    relativeTime: {
      future: "%s tới",
      past: "%s trước",
      s: "vài giây",
      ss: "%d giây",
      m: "1 phút",
      mm: "%d phút",
      h: "1 giờ",
      hh: "%d giờ",
      d: "1 ngày",
      dd: "%d ngày",
      M: "1 tháng",
      MM: "%d tháng",
      y: "1 năm",
      yy: "%d năm",
    },
  });
};

// Initialize the locale configuration
configureVietnameseLocale();

// Export moment instance with Vietnamese locale configured
export { moment };
