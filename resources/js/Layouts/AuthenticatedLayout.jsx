import Navbar from "@/Components/Navbar";

export default function AuthenticatedLayout({ children }) {
  return (
    <div>
      <Navbar />
      <main className="flex flex-col xl:flex-row flex-1">
        <div className="flex-1 mt-[4.3rem]">{children}</div>
      </main>
    </div>
  );
}
