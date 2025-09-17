export default function Checkbox({ className = "", ...props }) {
  return (
    <input
      {...props}
      type="checkbox"
      className={
        "rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-[#319528] shadow-sm focus:ring-[#319528] dark:focus:ring-[#319528] dark:focus:ring-offset-gray-800 " +
        className
      }
    />
  );
}
