import { useState, useEffect } from 'react';
import { getAttendanceRecap, verifyAttendance } from '@/features/dashboard/services/attendanceRecapService';
import type { AttendanceRecapData } from '@/features/dashboard/types/attendanceRecap';

export function useAttendanceRecap() {
  const [data, setData] = useState<AttendanceRecapData[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isVerifying, setIsVerifying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // PERBAIKAN: Pindahkan logika fetch ke dalam useEffect
  useEffect(() => {
    let isMounted = true; // Mencegah memory leak jika komponen di-unmount

    const fetchRecap = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const result = await getAttendanceRecap();
        if (isMounted) {
          setData(result);
        }
      } catch (err) {
        console.error(err);
        if (isMounted) {
          setError('Gagal memuat rekap absensi.');
        }
      } finally {
        if (isMounted) {
          setIsLoading(false);
        }
      }
    };

    fetchRecap();

    // Cleanup function
    return () => {
      isMounted = false;
    };
  }, []); // Array kosong agar hanya jalan sekali saat mount

  const handleVerify = async (ids: string[]) => {
    setIsVerifying(true);
    try {
      const success = await verifyAttendance(ids);
      if (success) {
        // Update state lokal agar langsung berubah tanpa fetch ulang
        setData((prev) => 
          prev.map(item => ids.includes(item.id) ? { ...item, isVerified: true } : item)
        );
        return true;
      }
      return false;
    } catch (err) {
      console.error(err);
      return false;
    } finally {
      setIsVerifying(false);
    }
  };

  return { data, isLoading, isVerifying, error, handleVerify };
}