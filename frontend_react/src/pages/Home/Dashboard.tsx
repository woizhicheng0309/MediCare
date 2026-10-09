import { useEffect, useState } from 'react';
import { Alert, Box, CircularProgress, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import { useAuth } from '../../contexts/useAuth';
import { getBenefits, getVisits, getEmployees, getBenefitPlans, getPendingVisits } from '../../services/api/medical';
import VisitTable from '../../components/VisitTable';
import { EmployeeVisitForm, HrWorkflow, HelpdeskReview } from '../../components/MedicalWorkflows';
import type { Benefits, Visit, Employee, BenefitPlan } from '../../types';

interface Overview { benefits?: Benefits | null; visits?: Visit[]; employees?: Employee[]; plans?: BenefitPlan[]; }
const money = (amount: number) => `TWD ${amount.toLocaleString('en-US', { maximumFractionDigits: 0 })}`;


export default function Dashboard() {
  const { user } = useAuth();
  const [overview, setOverview] = useState<Overview | null>(null);
  const [error, setError] = useState<string | null>(null);
  const role = user?.role;
  const [revision, setRevision] = useState(0);
  const reload = () => setRevision((value) => value + 1);

  useEffect(() => {
    const controller = new AbortController();
    async function load() {
      try {
        let data: Overview;
        if (role === 'employee') {
          const [benefits, visits] = await Promise.all([getBenefits(controller.signal), getVisits(controller.signal)]);
          data = { benefits, visits: visits.visits };
        } else if (role === 'hr') {
          const [employees, plans] = await Promise.all([getEmployees(controller.signal), getBenefitPlans(controller.signal)]);
          data = { employees: employees.employees, plans: plans.benefit_plans };
        } else if (role === 'helpdesk') {
          data = await getPendingVisits(controller.signal);
        } else { throw new Error('Your account has an unsupported role.'); }
        if (!controller.signal.aborted) { setOverview(data); setError(null); }
      } catch (caught) {
        if (!controller.signal.aborted) setError(caught instanceof Error ? caught.message : 'Unable to load your records.');
      }
    }
    void load();
    return () => controller.abort();
  }, [role, user?.id, revision]);

  if (error) return <Alert severity="error">{error}</Alert>;
  if (!overview) return <CircularProgress aria-label="Loading records" />;

  return <Stack spacing={3}>
    <Box><Typography variant="h4" component="h1">{role === 'employee' ? 'My healthcare benefits' : role === 'hr' ? 'Company overview' : 'Pending clinic visits'}</Typography>
      <Typography color="text.secondary" sx={{ mt: 1 }}>{role === 'employee' ? 'Your annual coverage and personal medical records.' : role === 'hr' ? 'Employees and benefit plans within your company.' : 'Client-company submissions awaiting Helpdesk review.'}</Typography></Box>
    {role === 'employee' && <>
      {overview.benefits ? <>
        <Typography variant="h6">{overview.benefits.plan_name} · {overview.benefits.year}</Typography>
        <Box sx={{ display: 'grid', gridTemplateColumns: { xs: '1fr', sm: 'repeat(3, 1fr)' }, gap: 2 }}>
          {[['Annual limit', overview.benefits.annual_limit], ['Used amount', overview.benefits.used_amount], ['Remaining balance', overview.benefits.remaining_balance]].map(([label, value]) => <Paper variant="outlined" key={label} sx={{ p: 3 }}><Typography color="text.secondary">{label}</Typography><Typography variant="h5" sx={{ mt: 1 }}>{money(Number(value))}</Typography></Paper>)}
        </Box>
      </> : <Alert severity="info">No benefit plan has been assigned for this year.</Alert>}
      <EmployeeVisitForm onSaved={reload} />
      <Typography variant="h6">My clinic visits</Typography><VisitTable visits={overview.visits ?? []} />
      <Typography variant="caption" color="text.secondary">Medication costs are included in the visit covered amount.</Typography>
    </>}
    {role === 'hr' && <>
      <Typography variant="h6">Employees</Typography>
      <TableContainer component={Paper} variant="outlined"><Table aria-label="Company employees"><TableHead><TableRow><TableCell>Name</TableCell><TableCell>Email</TableCell></TableRow></TableHead><TableBody>{overview.employees?.map((employee) => <TableRow key={employee.id}><TableCell>{employee.name}</TableCell><TableCell>{employee.email}</TableCell></TableRow>)}</TableBody></Table></TableContainer>
      <Typography variant="h6">Benefit plans</Typography>
      <TableContainer component={Paper} variant="outlined"><Table aria-label="Company benefit plans"><TableHead><TableRow><TableCell>Plan</TableCell><TableCell>Annual limit</TableCell></TableRow></TableHead><TableBody>{overview.plans?.map((plan) => <TableRow key={plan.id}><TableCell>{plan.name}</TableCell><TableCell>{money(plan.annual_limit_cents / 100)}</TableCell></TableRow>)}</TableBody></Table></TableContainer>
      <HrWorkflow employees={overview.employees ?? []} plans={overview.plans ?? []} />
    </>}
    {role === 'helpdesk' && <HelpdeskReview visits={overview.visits ?? []} onSaved={reload} />}
  </Stack>;
}
