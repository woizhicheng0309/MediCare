import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Alert, Button, CircularProgress, Dialog, DialogActions, DialogContent, DialogTitle, MenuItem, Paper, Stack, TextField, Typography } from '@mui/material';
import { ApiError } from '../services/api/auth';
import { assignBenefitPlan, decideVisit, getEmployeeVisits, recordVisit } from '../services/api/medical';
import type { BenefitPlan, Employee, Visit } from '../types';
import VisitTable from './VisitTable';

const messageOf = (error: unknown) => error instanceof Error ? error.message : 'Unable to complete this request.';
const today = () => { const date = new Date(); return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`; };

export function EmployeeVisitForm({ onSaved }: { onSaved: () => void }) {
  const [clinic, setClinic] = useState('');
  const [date, setDate] = useState(today);
  const [amount, setAmount] = useState('');
  const [diagnosis, setDiagnosis] = useState('');
  const [leaveStart, setLeaveStart] = useState('');
  const [leaveEnd, setLeaveEnd] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [success, setSuccess] = useState<string | null>(null);
  const fieldError = (field: string) => fieldErrors[field]?.[0];

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError(null); setFieldErrors({}); setSuccess(null);
    const coveredAmount = Number(amount);
    if (!clinic.trim() || !amount || !Number.isSafeInteger(coveredAmount) || coveredAmount < 0) { setError('Enter a clinic and a whole TWD amount of zero or more.'); return; }
    setBusy(true);
    try {
      // Send absent optional fields as null; ownership and status remain server-controlled.
      const response = await recordVisit({
        clinic: clinic.trim(), visited_on: date, covered_amount: coveredAmount,
        diagnosis: diagnosis.trim() || null,
        sick_leave_started_on: leaveStart || null,
        sick_leave_ended_on: leaveEnd || null,
      });
      setSuccess(response.message);
      setClinic(''); setAmount(''); setDiagnosis(''); setLeaveStart(''); setLeaveEnd('');
      onSaved();
    }
    catch (caught) {
      setError(messageOf(caught));
      if (caught instanceof ApiError && caught.status === 422) setFieldErrors(caught.errors);
    }
    finally { setBusy(false); }
  }

  return <Paper variant="outlined" sx={{ p: 3 }}><Stack component="form" onSubmit={submit} spacing={2}>
    <Typography variant="h6">Record a clinic visit</Typography>
    <Typography variant="body2" color="text.secondary">New visits await Helpdesk review. Pending visits do not reduce your balance.</Typography>
    {error && <Alert severity="error">{error}</Alert>}{success && <Alert severity="success">{success}</Alert>}
    <TextField label="Clinic name" required value={clinic} disabled={busy} onChange={(event) => setClinic(event.target.value)} error={Boolean(fieldError('clinic'))} helperText={fieldError('clinic')} slotProps={{ htmlInput: { maxLength: 255 } }} />
    <TextField label="Visit date" type="date" required value={date} disabled={busy} onChange={(event) => setDate(event.target.value)} error={Boolean(fieldError('visited_on'))} helperText={fieldError('visited_on')} slotProps={{ inputLabel: { shrink: true } }} />
    <TextField label="Covered amount (TWD)" type="number" required value={amount} disabled={busy} onChange={(event) => setAmount(event.target.value)} error={Boolean(fieldError('covered_amount'))} helperText={fieldError('covered_amount')} slotProps={{ htmlInput: { min: 0, step: 1 } }} />
    <TextField label="Diagnosis" value={diagnosis} disabled={busy} onChange={(event) => setDiagnosis(event.target.value)} error={Boolean(fieldError('diagnosis'))} helperText={fieldError('diagnosis') ?? 'Optional'} slotProps={{ htmlInput: { maxLength: 255 } }} />
    {/* Date inputs provide early feedback; the API still validates both dates. */}
    <Stack direction={{ xs: 'column', sm: 'row' }} spacing={2}>
      <TextField label="Sick leave start date" type="date" fullWidth required={Boolean(leaveEnd)} value={leaveStart} disabled={busy} onChange={(event) => setLeaveStart(event.target.value)} error={Boolean(fieldError('sick_leave_started_on'))} helperText={fieldError('sick_leave_started_on')} slotProps={{ inputLabel: { shrink: true } }} />
      <TextField label="Sick leave end date" type="date" fullWidth required={Boolean(leaveStart)} value={leaveEnd} disabled={busy} onChange={(event) => setLeaveEnd(event.target.value)} error={Boolean(fieldError('sick_leave_ended_on'))} helperText={fieldError('sick_leave_ended_on')} slotProps={{ inputLabel: { shrink: true }, htmlInput: { min: leaveStart || undefined } }} />
    </Stack>
    <Typography variant="body2" color="text.secondary">Sick leave is optional. If provided, enter both dates; the end date must be on or after the start date.</Typography>
    <Button type="submit" variant="contained" disabled={busy}>{busy ? 'Saving...' : 'Save visit'}</Button>
  </Stack></Paper>;
}

export function HrWorkflow({ employees, plans }: { employees: Employee[]; plans: BenefitPlan[] }) {
  const [employeeId, setEmployeeId] = useState('');
  const [planId, setPlanId] = useState('');
  const [visits, setVisits] = useState<Visit[] | null>(null);
  const [loading, setLoading] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  useEffect(() => {
    if (!employeeId) return;
    const controller = new AbortController();
    async function load() {
      setLoading(true); setVisits(null); setError(null);
      try { const result = await getEmployeeVisits(Number(employeeId), controller.signal); if (!controller.signal.aborted) setVisits(result.visits); }
      catch (caught) { if (!controller.signal.aborted) setError(messageOf(caught)); }
      finally { if (!controller.signal.aborted) setLoading(false); }
    }
    void load(); return () => controller.abort();
  }, [employeeId]);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); setError(null); setSuccess(null);
    try { const result = await assignBenefitPlan(Number(employeeId), Number(planId)); setSuccess(result.message); }
    catch (caught) { setError(messageOf(caught)); }
    finally { setBusy(false); }
  }
  return <Stack spacing={2}>
    <Typography variant="h6">Employee medical records and benefit assignment</Typography>
    <TextField select label="Employee" value={employeeId} disabled={busy || !employees.length} onChange={(event) => { setEmployeeId(event.target.value); setSuccess(null); }}>
      {employees.map((employee) => <MenuItem key={employee.id} value={String(employee.id)}>{employee.name}</MenuItem>)}
    </TextField>
    {!employees.length && <Alert severity="info">Your company has no employees to display.</Alert>}
    {error && <Alert severity="error">{error}</Alert>}{success && <Alert severity="success">{success}</Alert>}
    {employeeId && <>
      <Stack component="form" onSubmit={submit} spacing={2}>
        <TextField select label="Benefit plan" required value={planId} disabled={busy || !plans.length} onChange={(event) => setPlanId(event.target.value)}>
          {plans.map((plan) => <MenuItem key={plan.id} value={String(plan.id)}>{plan.name} · TWD {(plan.annual_limit_cents / 100).toLocaleString('en-US')}</MenuItem>)}
        </TextField>
        <Typography variant="body2">This assigns or replaces the current calendar-year plan. Approved usage for that year is preserved.</Typography>
        <Button type="submit" variant="contained" disabled={busy || !planId}>{busy ? 'Assigning...' : 'Assign benefit plan'}</Button>
      </Stack>
      <Typography variant="h6">Medical records</Typography>
      {loading ? <CircularProgress aria-label="Loading employee medical records" /> : visits && <VisitTable visits={visits} />}
    </>}
  </Stack>;
}

export function HelpdeskReview({ visits, onSaved }: { visits: Visit[]; onSaved: () => void }) {
  const [decision, setDecision] = useState<{ visit: Visit; status: 'approved' | 'rejected' } | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  async function confirm() {
    if (!decision) return;
    setBusy(true); setError(null); setSuccess(null);
    try { const result = await decideVisit(decision.visit.id, decision.status); setSuccess(result.message); setDecision(null); onSaved(); }
    catch (caught) { setError(messageOf(caught)); setDecision(null); onSaved(); }
    finally { setBusy(false); }
  }
  return <Stack spacing={2}>
    {error && <Alert severity="error">{error}</Alert>}{success && <Alert severity="success">{success}</Alert>}
    <VisitTable visits={visits} includeEmployee busy={busy} onDecision={(visit, status) => { setError(null); setSuccess(null); setDecision({ visit, status }); }} />
    <Dialog open={decision !== null} onClose={() => { if (!busy) setDecision(null); }}>
      <DialogTitle>{decision?.status === 'approved' ? 'Approve visit?' : 'Reject visit?'}</DialogTitle>
      <DialogContent><Typography>{decision?.visit.user?.name} · {decision?.visit.clinic} · TWD {((decision?.visit.covered_amount_cents ?? 0) / 100).toLocaleString('en-US')}</Typography><Typography sx={{ mt: 2 }}>Approval includes this visit in the employee's annual usage. Rejection leaves the balance unchanged.</Typography></DialogContent>
      <DialogActions><Button disabled={busy} onClick={() => setDecision(null)}>Cancel</Button><Button variant="contained" disabled={busy} onClick={confirm}>{busy ? 'Saving...' : 'Confirm decision'}</Button></DialogActions>
    </Dialog>
  </Stack>;
}
