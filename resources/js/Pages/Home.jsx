import Navbar from "@/Components/Navbar";

export default function Home({ title }) {
    return (
        <div>
            <Navbar />
            <h1>{title}</h1>
            <div class="container mt-5">
                <button class="btn btn-primary">Test Button</button>
            </div>
        </div>
    );
}
