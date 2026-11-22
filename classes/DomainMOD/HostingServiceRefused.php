<?php
/**
 * /classes/DomainMOD/HostingServiceRefused.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD;

/*
 * Thrown by a domain write when the hosting service it would link to can't be used (it no longer exists, or it's
 * cancelled), and the page shows the message under its Hosting Service menu, or, with the BUSY code, when the hosting
 * lock couldn't be had in time, and the page shows the message at the top. Nothing has been written either way.
 */
class HostingServiceRefused extends \RuntimeException
{
    const BUSY = 1;
} //@formatter:on
