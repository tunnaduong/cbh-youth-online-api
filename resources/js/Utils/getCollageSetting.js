// hàm tạo layout + height tự động
export default function getCollageSetting(photos) {
  const count = photos.length;

  if (count === 1) {
    return {
      width: "100%",
      height: ["100%"],
      layout: [1],
    };
  }

  if (count === 2) {
    return {
      width: "100%",
      height: ["100%"],
      layout: [2],
    };
  }

  if (count === 3) {
    return {
      width: "100%",
      height: ["100%"],
      layout: [3],
    };
  }

  // mặc định cho 4 ảnh trở lên
  return {
    width: "100%",
    height: ["60%", "39%"],
    layout: [2, 3], // 2 ảnh hàng trên, 3 ảnh hàng dưới
  };
}
