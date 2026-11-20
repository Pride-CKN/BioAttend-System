import { useState } from "react";
import { Link, useParams } from "react-router";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type {
  Division,
  DivisionInput,
  DivisionStatus,
  Unit,
  UnitInput,
} from "@/features/division/types/division.type";
import { divisionStatuses } from "@/features/division/lib/divisionConfig";
import {
  type DivisionErrors,
  validateDivision,
} from "@/features/division/lib/divisionValidation";
import { useDivisionForm } from "@/features/division/hooks/useDivisionForm";
import {
  addDivisionUnit,
  changeDivisionUnitStatus,
} from "@/features/division/services/divisionAction.Service";
import { PageBreadcrumb } from "@/components/page-breadcrumb";

function Field({
  label,
  error,
  children,
}: {
  label: string;
  error?: string;
  children: React.ReactNode;
}) {
  return (
    <label className="flex flex-col gap-1 text-sm">
      <span className="font-medium">{label}</span>
      {children}
      {error && <span className="text-xs text-red-600">{error}</span>}
    </label>
  );
}

// Form dipisah dari loader agar state form diinisialisasi sekali dari `initial` (tanpa effect sinkronisasi).
function UnitManager({ division }: { division: Division }) {
  const [units, setUnits] = useState(division.units);
  const [values, setValues] = useState<UnitInput>({
    code: "",
    name: "",
    employeeCodeDigit: 1,
    headId: null,
    status: "Aktif",
  });
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const add = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!values.code.trim() || !values.name.trim()) {
      setError("Kode dan nama unit wajib diisi.");
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const created = await addDivisionUnit(division.id, values);
      setUnits((current) => [...current, created]);
      setValues({
        code: "",
        name: "",
        employeeCodeDigit: 1,
        headId: null,
        status: "Aktif",
      });
    } catch (reason) {
      setError(
        reason instanceof Error ? reason.message : "Gagal menambahkan unit.",
      );
    } finally {
      setSaving(false);
    }
  };

  const toggle = async (unit: Unit) => {
    const status: DivisionStatus =
      unit.status === "Aktif" ? "Nonaktif" : "Aktif";
    setError(null);
    try {
      await changeDivisionUnitStatus(division.id, unit.id, status);
      setUnits((current) =>
        current.map((item) =>
          item.id === unit.id ? { ...item, status } : item,
        ),
      );
    } catch (reason) {
      setError(
        reason instanceof Error
          ? reason.message
          : "Gagal mengubah status unit.",
      );
    }
  };

  return (
    <section className="space-y-3 border-t pt-4">
      <div>
        <h2 className="text-sm font-semibold">Unit dalam {division.name}</h2>
        <p className="text-xs text-muted-foreground">
          Unit adalah bagian dari Division dan dikelola terpisah.
        </p>
      </div>
      {error && (
        <p role="alert" className="text-xs text-red-700">
          {error}
        </p>
      )}
      {units.length > 0 && (
        <ul className="divide-y rounded-lg border">
          {units.map((unit) => (
            <li
              key={unit.id}
              className="flex items-center justify-between gap-3 px-3 py-2 text-sm"
            >
              <span>
                <strong>{unit.code}</strong> · {unit.name}
                <span className="ml-2 text-xs text-muted-foreground">
                  Digit {unit.employeeCodeDigit ?? "—"}
                </span>
              </span>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={() => void toggle(unit)}
              >
                {unit.status === "Aktif" ? "Nonaktifkan" : "Aktifkan"}
              </Button>
            </li>
          ))}
        </ul>
      )}
      <form
        onSubmit={(event) => void add(event)}
        className="grid gap-2 sm:grid-cols-[1fr_1.5fr_120px_auto]"
      >
        <Input
          placeholder="Kode unit"
          value={values.code}
          onChange={(event) =>
            setValues((current) => ({
              ...current,
              code: event.target.value.toUpperCase(),
            }))
          }
        />
        <Input
          placeholder="Nama unit"
          value={values.name}
          onChange={(event) =>
            setValues((current) => ({ ...current, name: event.target.value }))
          }
        />
        <Select
          value={String(values.employeeCodeDigit)}
          onValueChange={(value) =>
            setValues((current) => ({
              ...current,
              employeeCodeDigit: Number(value),
            }))
          }
        >
          <SelectTrigger aria-label="Digit Employee ID">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {Array.from({ length: 9 }, (_, index) => index + 1).map((digit) => (
              <SelectItem key={digit} value={String(digit)}>
                Digit {digit}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
        <Button type="submit" disabled={saving}>
          {saving ? "Menyimpan…" : "+ Unit"}
        </Button>
      </form>
    </section>
  );
}

function DivisionFormBody({
  initial,
  division,
  headOptions,
  isEdit,
  saving,
  error,
  onSave,
}: {
  initial: DivisionInput;
  division: Division | null;
  headOptions: Array<{ id: string; label: string }>;
  isEdit: boolean;
  saving: boolean;
  error: string | null;
  onSave: (v: DivisionInput) => void;
}) {
  const [values, setValues] = useState<DivisionInput>(initial);
  const [errors, setErrors] = useState<DivisionErrors>({});
  const set = <K extends keyof DivisionInput>(k: K, v: DivisionInput[K]) =>
    setValues((s) => ({ ...s, [k]: v }));

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const next = validateDivision(values);
    setErrors(next);
    if (Object.keys(next).length === 0) onSave(values);
  };

  return (
    <Card className="max-w-3xl overflow-hidden py-0">
      <form onSubmit={submit} noValidate>
        <CardContent className="flex flex-col gap-4 p-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Kode" error={errors.code}>
              <Input
                value={values.code}
                aria-invalid={!!errors.code}
                onChange={(e) => set("code", e.target.value.toUpperCase())}
              />
            </Field>
            <Field label="Status">
              <Select
                value={values.status}
                onValueChange={(value) =>
                  set("status", value as DivisionInput["status"])
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {divisionStatuses.map((status) => (
                    <SelectItem key={status} value={status}>
                      {status}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>
          <Field label="Nama Division" error={errors.name}>
            <Input
              value={values.name}
              aria-invalid={!!errors.name}
              onChange={(e) => set("name", e.target.value)}
            />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="KADIV Penanggung Jawab">
              <Select
                value={values.headId ?? "none"}
                onValueChange={(value) =>
                  set("headId", value === "none" ? null : value)
                }
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">— Belum ditentukan —</SelectItem>
                  {headOptions.map((head) => (
                    <SelectItem key={head.id} value={head.id}>
                      {head.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          </div>

          {division && <UnitManager key={division.id} division={division} />}

          {error && (
            <p
              role="alert"
              className="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
              {error}
            </p>
          )}
        </CardContent>

        <div className="flex justify-end gap-2 border-t px-4 py-3">
          <Button
            type="button"
            variant="outline"
            className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0"
            render={<Link to="/division" />}
          >
            Batal
          </Button>
          <Button
            type="submit"
            variant="outline"
            className="rounded-full border-[#080808] bg-transparent px-4 text-[#080808] transition duration-200 hover:-translate-y-0.5 hover:border-[#EA232A] hover:bg-white hover:text-[#EA232A] active:translate-y-0"
            disabled={saving}
          >
            {saving
              ? "Menyimpan…"
              : isEdit
                ? "Simpan Perubahan"
                : "Tambah Divisi"}
          </Button>
        </div>
      </form>
    </Card>
  );
}

export default function divisionForm() {
  const { id } = useParams();
  const f = useDivisionForm(id);

  return (
    <div className="flex flex-col gap-4 p-4 md:p-6">
      <PageBreadcrumb
        items={[
          { label: "Division", to: "/division" },
          { label: f.isEdit ? "Edit Division" : "Tambah Divisi" },
        ]}
      />
      <header>
        <h1 className="text-xl font-bold">
          {f.isEdit ? "Edit Division" : "Tambah Divisi"}
        </h1>
        <p className="text-xs text-muted-foreground">
          Kelola struktur Division dan Unit.
        </p>
      </header>

      {f.notFound ? (
        <p className="text-sm text-muted-foreground">
          Division tidak ditemukan.{" "}
          <Link to="/division" className="text-red-700 hover:underline">
            Kembali ke daftar
          </Link>
        </p>
      ) : !f.initial ? (
        <p className="text-sm text-muted-foreground">{f.error ?? "Memuat…"}</p>
      ) : (
        // key = id → form di-reset bila pindah dari edit satu ke edit lainnya
        <DivisionFormBody
          key={id ?? "new"}
          initial={f.initial}
          division={f.current}
          headOptions={f.headOptions}
          isEdit={f.isEdit}
          saving={f.saving}
          error={f.error}
          onSave={f.save}
        />
      )}
    </div>
  );
}
