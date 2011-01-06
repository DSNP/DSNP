/*
 *  Copyright 2003 Adrian Thurston <thurston@complang.org>
 */

/*  This file is part of Colm.
 *
 *  Colm is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 * 
 *  Colm is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 * 
 *  You should have received a copy of the GNU General Public License
 *  along with Colm; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA 
 */

#ifndef _BUFFER_H
#define _BUFFER_H

#include <string.h>


/* An automatically grown buffer for collecting tokens. Always reuses space;
 * never down resizes. */
struct Buffer
{

	Buffer()
	{
		data = new char[initialSize];
		allocated = initialSize;
		length = 0;
	}
	~Buffer() { delete[] data; }

	void append( char p )
	{
		if ( length == allocated ) {
			allocated *= 2;
			char *newData = new char[allocated];
			memcpy( newData, data, length );
			delete[] data;
			data = newData;
		}
		data[length++] = p;
	}
		
	void clear() { length = 0; }

	static const int initialSize = 4096;

	char *data;
	int allocated;
	int length;
};

#endif /* _BUFFER_H */
