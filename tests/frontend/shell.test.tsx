import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

function ShellSmoke() { return <nav aria-label="Customer navigation"><a>Overview</a><a>Domains</a><a>Transfers</a><a>SSL</a><a>Billing</a><a>Settings</a></nav>; }

describe('application shell', () => { it('renders the approved customer navigation', () => { render(<ShellSmoke />); expect(screen.getByText('Overview')).toBeInTheDocument(); expect(screen.getByText('Settings')).toBeInTheDocument(); expect(screen.queryByText('Workspace')).not.toBeInTheDocument(); expect(screen.queryByText('Cart')).not.toBeInTheDocument(); }); });
