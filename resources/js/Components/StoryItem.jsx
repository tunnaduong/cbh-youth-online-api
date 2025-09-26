export default function StoryItem({ story }) {
  if (story.type === "image") {
    return <img src={story.media_url} alt="story" className="w-full h-full object-cover" />;
  } else if (story.type === "video") {
    return (
      <video autoPlay muted className="w-full h-full object-cover">
        <source src={story.media_url} type="video/mp4" />
      </video>
    );
  }
}
