/*
 *  pix_scanline.h
 *  gem_darwin
 *
 *  Created by chris clepper on Mon Oct 07 2002.
 *  Copyright (c) 2002 __MyCompanyName__. All rights reserved.
 *
 */

#ifndef INCLUDE_pix_scanline_H_ 
#define INCLUDE_pix_scanline_H_ 

#include "Base/GemPixObj.h"

/*-----------------------------------------------------------------
-------------------------------------------------------------------
CLASS
    pix_scanline
    
    

KEYWORDS
    pix
    yuv
    
DESCRIPTION

   does manipulations of the rows of pixels by copying, deleteing or moving them
   
-----------------------------------------------------------------*/

class GEM_EXTERN pix_scanline : public GemPixObj
{
CPPEXTERN_HEADER(pix_scanline, GemPixObj)

    public:

	    //////////
	    // Constructor
    	pix_scanline();
    	
    protected:
    	
    	//////////
    	// Destructor
    	virtual ~pix_scanline();

    	//////////
    	// Do the processing
    	virtual void 	processRGBAImage(imageStruct &image);
    	
        //////////
    	// Do the YUV processing
    	virtual void 	processYUVImage(imageStruct &image);
    //    virtual void 	processYUVAltivec(imageStruct &image);
        
        unsigned char  *saved;
        int		m_interlace,m_mode;
        t_inlet         *inletScanline;

        
    private:
    
    	//////////
    	// Static member functions
    	
        static void rollCallback       (void *data, t_floatarg value);
        static void modeCallback       (void *data, t_floatarg value);


};

#endif

