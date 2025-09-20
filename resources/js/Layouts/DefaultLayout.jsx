import Navbar from "@/Components/Navbar";

export default function DefaultLayout({ children, activeNav }) {
  return (
    <div>
      <Navbar activeNav={activeNav} />
      <div className="flex flex-col xl:flex-row flex-1">
        <div className="flex-1 mt-[4.3rem]">{children}</div>
      </div>
    </div>
  );
}
