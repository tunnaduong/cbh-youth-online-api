import { useState, useRef, useEffect } from "react";
import { Button } from "antd";
import { PlayCircle, PauseCircle, Volume2, VolumeX } from "lucide-react";

export default function AudioPlayer({ src, title, artist, className, thumbnail }) {
  const audioRef = useRef(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);
  const [volume, setVolume] = useState(1);
  const [isMuted, setIsMuted] = useState(false);

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    const updateTime = () => setCurrentTime(audio.currentTime);
    const updateDuration = () => setDuration(audio.duration);
    const handleEnded = () => setIsPlaying(false);

    audio.addEventListener("timeupdate", updateTime);
    audio.addEventListener("loadedmetadata", updateDuration);
    audio.addEventListener("ended", handleEnded);

    return () => {
      audio.removeEventListener("timeupdate", updateTime);
      audio.removeEventListener("loadedmetadata", updateDuration);
      audio.removeEventListener("ended", handleEnded);
    };
  }, []);

  const togglePlay = () => {
    const audio = audioRef.current;
    if (!audio) return;

    if (isPlaying) {
      audio.pause();
    } else {
      audio.play();
    }
    setIsPlaying(!isPlaying);
  };

  const handleSeek = (value) => {
    const audio = audioRef.current;
    if (!audio) return;

    const newTime = value[0];
    audio.currentTime = newTime;
    setCurrentTime(newTime);
  };

  const handleVolumeChange = (value) => {
    const audio = audioRef.current;
    if (!audio) return;

    const newVolume = value[0];
    audio.volume = newVolume;
    setVolume(newVolume);
    setIsMuted(newVolume === 0);
  };

  const toggleMute = () => {
    const audio = audioRef.current;
    if (!audio) return;

    if (isMuted) {
      audio.volume = volume;
      setIsMuted(false);
    } else {
      audio.volume = 0;
      setIsMuted(true);
    }
  };

  const formatTime = (time) => {
    const minutes = Math.floor(time / 60);
    const seconds = Math.floor(time % 60);
    return `${minutes}:${seconds.toString().padStart(2, "0")}`;
  };

  return (
    <div className={`bg-card rounded-lg border shadow-sm ${className}`}>
      <audio ref={audioRef} src={src} preload="metadata" />

      {/* Play/Pause Button */}
      {thumbnail && (
        <a
          href={thumbnail}
          target="_blank"
          className="rounded-lg overflow-hidden shadow-sm hover:shadow-lg transition-all duration-300 group"
        >
          <img
            src={thumbnail}
            alt={title}
            className="w-full h-[80px] object-cover rounded-lg transform group-hover:scale-105 transition-transform duration-300"
          />
        </a>
      )}
      <div className="flex items-center gap-4">
        <button
          onClick={togglePlay}
          className="w-10 h-10 bg-gradient-to-br from-[#319527] to-[#2a7a22] rounded-full flex items-center justify-center shadow-md hover:shadow-lg hover:from-[#2a7a22] hover:to-[#1f5c19] transform hover:scale-105 transition-all duration-300"
        >
          {isPlaying ? (
            <PauseCircle className="w-5 h-5 text-white" />
          ) : (
            <PlayCircle className="w-5 h-5 text-white" />
          )}
        </button>

        {/* Track Info */}
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-4">
            <div className="min-w-0 flex-1">
              <h3 className="font-medium text-sm truncate">{title}</h3>
              {artist && <p className="text-xs text-muted-foreground truncate">{artist}</p>}
            </div>

            {/* Time Display */}
            <div className="text-xs text-muted-foreground shrink-0">
              {formatTime(currentTime)} / {formatTime(duration)}
            </div>
          </div>

          {/* Progress Bar */}
          <div className="mt-2">
            <input
              type="range"
              value={currentTime}
              max={duration || 100}
              step={1}
              onChange={(e) => handleSeek([parseFloat(e.target.value)])}
              className="w-full h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
          </div>
        </div>

        {/* Volume Controls */}
        <div className="flex items-center gap-2 shrink-0">
          <button
            onClick={toggleMute}
            className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          >
            {isMuted || volume === 0 ? (
              <VolumeX className="w-4 h-4" />
            ) : (
              <Volume2 className="w-4 h-4" />
            )}
          </button>

          <div className="w-20">
            <input
              type="range"
              value={isMuted ? 0 : volume}
              max={1}
              step={0.1}
              onChange={(e) => handleVolumeChange([parseFloat(e.target.value)])}
              className="w-full h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
            />
          </div>
        </div>
      </div>
    </div>
  );
}
