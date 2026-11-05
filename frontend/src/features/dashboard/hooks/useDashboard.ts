import { useState, useEffect } from 'react';
import { getDashboardData } from '@/features/dashboard/services/dashboardService';
import type { DashboardDataType } from '@/features/dashboard/types/dashboard';

export function useDashboard() {
  const [data, setData] = useState<DashboardDataType | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchData = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const result = await getDashboardData();
        setData(result);
      } catch (err) {
        // Solusi 2: Gunakan console.error agar 'err' terpakai
        console.error("Error fetching dashboard:", err);
        setError('Gagal memuat data dashboard.');
      } finally {
        setIsLoading(false);
      }
    };

    fetchData();
  }, []);

  return { data, isLoading, error };
}