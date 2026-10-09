import { Alert, Button, Chip, Paper, Stack, Table, TableBody, TableCell, TableContainer, TableHead, TableRow, Typography } from '@mui/material';
import type { Visit } from '../types';
const money = (amount: number) => `TWD ${amount.toLocaleString('en-US', { maximumFractionDigits: 0 })}`;

export default function VisitTable({ visits, includeEmployee = false, onDecision, busy = false }: { visits: Visit[]; includeEmployee?: boolean; onDecision?: (visit: Visit, status: 'approved' | 'rejected') => void; busy?: boolean }) {
  if (!visits.length) return <Alert severity="info">No visits to display.</Alert>;
  return <TableContainer component={Paper} variant="outlined"><Table aria-label="Clinic visits">
    <TableHead><TableRow>{includeEmployee && <TableCell>Employee / company</TableCell>}<TableCell>Visit date</TableCell><TableCell>Clinic</TableCell><TableCell>Covered amount</TableCell><TableCell>Status</TableCell><TableCell>Medications</TableCell>{onDecision && <TableCell>Review</TableCell>}</TableRow></TableHead>
    <TableBody>{visits.map((visit) => <TableRow key={visit.id}>
      {includeEmployee && <TableCell>{visit.user?.name ?? 'Unassigned'}<Typography variant="caption" sx={{ display: 'block' }} color="text.secondary">{visit.user?.company?.name ?? 'No company'}</Typography></TableCell>}
      <TableCell>{visit.visited_on}</TableCell><TableCell>{visit.clinic}</TableCell><TableCell>{money(visit.covered_amount_cents / 100)}</TableCell>
      <TableCell><Chip size="small" label={visit.status} color={visit.status === 'approved' ? 'success' : visit.status === 'rejected' ? 'error' : 'warning'} /></TableCell>
      <TableCell>{visit.medications?.length ? visit.medications.map((medication) => <Typography variant="body2" key={medication.id}>{medication.name} · {money(medication.covered_amount_cents / 100)}{medication.dosage && ` · ${medication.dosage}`}{medication.instructions && ` · ${medication.instructions}`}</Typography>) : '—'}</TableCell>
      {onDecision && <TableCell>{visit.user ? <Stack direction="row" spacing={1}><Button disabled={busy} onClick={() => onDecision(visit, 'approved')}>Approve</Button><Button color="error" disabled={busy} onClick={() => onDecision(visit, 'rejected')}>Reject</Button></Stack> : <Typography variant="caption">Employee assignment required</Typography>}</TableCell>}
    </TableRow>)}</TableBody>
  </Table></TableContainer>;
}

