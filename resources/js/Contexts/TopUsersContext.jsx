import { createContext, useContext, useState, useEffect } from "react";

const TopUsersContext = createContext();

export const useTopUsers = () => {
  const context = useContext(TopUsersContext);
  if (!context) {
    throw new Error("useTopUsers must be used within a TopUsersProvider");
  }
  return context;
};

export const TopUsersProvider = ({ children }) => {
  const [topUsers, setTopUsers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [lastFetchTime, setLastFetchTime] = useState(null);
  const [isInitialLoad, setIsInitialLoad] = useState(true);

  const fetchTopUsers = async (forceRefresh = false) => {
    // If data exists and it's been less than 5 minutes, don't refetch unless forced
    const fiveMinutesAgo = Date.now() - 5 * 60 * 1000;
    if (!forceRefresh && topUsers.length > 0 && lastFetchTime && lastFetchTime > fiveMinutesAgo) {
      setLoading(false);
      return;
    }

    try {
      setLoading(true);
      setError(null);
      const response = await fetch("/v1.0/users/top-active");

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      console.log("Top users data:", data);
      setTopUsers(data);
      setLastFetchTime(Date.now());
      setIsInitialLoad(false);
    } catch (err) {
      setError(err.message);
      console.error("Error fetching top users:", err);
      setIsInitialLoad(false);
    } finally {
      setLoading(false);
    }
  };

  const refreshTopUsers = () => {
    fetchTopUsers(true);
  };

  useEffect(() => {
    fetchTopUsers();
  }, []);

  const value = {
    topUsers,
    loading,
    error,
    isInitialLoad,
    refreshTopUsers,
    fetchTopUsers,
  };

  return <TopUsersContext.Provider value={value}>{children}</TopUsersContext.Provider>;
};
