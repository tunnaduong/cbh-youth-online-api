import Navbar from "@/Components/Navbar";

export default function HomeLayout({ children }) {
    return (
        <div>
            <Navbar />
            <div className="mt-[4.3rem]">{children}</div>
        </div>
    );
}
