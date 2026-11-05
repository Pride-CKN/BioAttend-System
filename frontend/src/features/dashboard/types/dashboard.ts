export type KpiCardType = {
  title: string;
  val: string;
  sub: string;
  icon: string;
  color: string;
  subColor: string;
  borderColor: string;
};

export type TrendDataType = { date: string; hadir: number };
export type DonutDataType = { name: string; value: number; color: string };
export type BarDataType = { name: string; value: number };
export type TableDataType = { 
  name: string; 
  dept: string; 
  status: string; 
  in: string; 
  out: string; 
  dur: string; 
  note: string; 
};

export type DashboardDataType = {
  kpiCards: KpiCardType[];
  trendData: TrendDataType[];
  donutData: DonutDataType[];
  barData: BarDataType[];
  tableData: TableDataType[];
};