////////////////////////////////////////////////////////
//
// GEM - Graphics Environment for Multimedia
//
// zmoelnig@iem.kug.ac.at
//
// Implementation file
//
//    Copyright (c) 1997-1998 Mark Danks.
//    Copyright (c) G�nther Geiger.
//    Copyright (c) 2001-2003 IOhannes m zmoelnig. forum::f�r::uml�ute. IEM
//    For information on usage and redistribution, and for a DISCLAIMER OF ALL
//    WARRANTIES, see the file, "GEM.LICENSE.TERMS" in this distribution.
//
/////////////////////////////////////////////////////////


/////////////////////////////////////////////////////////
//
// pix_tIIR
//
//   IOhannes m zmoelnig
//   mailto:zmoelnig@iem.kug.ac.at
//
//   this code is published under the Gnu GeneralPublicLicense that should be distributed with gem & pd
//
/////////////////////////////////////////////////////////

#include "pix_tIIR.h"
#include <string.h>

CPPEXTERN_NEW_WITH_TWO_ARGS(pix_tIIR, t_floatarg, A_DEFFLOAT, t_floatarg, A_DEFFLOAT)

/////////////////////////////////////////////////////////
//
// pix_tIIR
//
/////////////////////////////////////////////////////////
// Constructor
//
/////////////////////////////////////////////////////////
pix_tIIR :: pix_tIIR(t_floatarg fb_numf=1, t_floatarg ff_numf=1)
{ 
  int fb_num = (fb_numf>0)?(int)fb_numf:0;
  int ff_num = (ff_numf>0)?(int)ff_numf:0;
  ff_count=ff_num;fb_count=fb_num;
  fb_num++;ff_num++;
  m_inlet = new t_inlet*[fb_num+ff_num];
  t_inlet **inlet = m_inlet;

  m_fb = new t_float[fb_num];
  m_ff = new t_float[ff_num];

  int i=0;
  while(i<fb_num){
    m_fb[i]=0.0;
    *inlet++=floatinlet_new(this->x_obj, m_fb+i);
    i++;
  }
  m_fb[0]=1.0;
  i=0;
  while(i<ff_num){
    m_ff[i]=0.0;
    *inlet++=floatinlet_new(this->x_obj, m_ff+i);
    i++;
  }
  m_ff[0]=1.0;

  set = false;
  set_zero = false;

  m_bufnum=(fb_num>ff_num)?fb_num:ff_num;
  m_counter=0;

  m_buffer.xsize=64;
  m_buffer.ysize=64;
  m_buffer.csize=4;
  m_buffer.format=GL_RGBA;
  m_buffer.allocate(m_buffer.xsize*m_buffer.ysize*m_buffer.csize*m_bufnum);
}

/////////////////////////////////////////////////////////
// Destructor
//
/////////////////////////////////////////////////////////
pix_tIIR :: ~pix_tIIR()
{
  // clean my buffer
}

/////////////////////////////////////////////////////////
// processImage
//
/////////////////////////////////////////////////////////
void pix_tIIR :: processImage(imageStruct &image)
{
  t_float f;
  int i, j;
  int imagesize = image.xsize*image.ysize*image.csize;
  unsigned char *dest, *source;

  // assume that the pix_size does not change !
  // if (oldsize<newsize){}
  dest=m_buffer.data;
  m_buffer.reallocate(image.xsize*image.ysize*image.csize*m_bufnum);
  if (m_buffer.xsize!=image.xsize || m_buffer.ysize!=image.ysize || m_buffer.format!=image.format){
    m_buffer.xsize=image.xsize;
    m_buffer.ysize=image.ysize;
    m_buffer.csize=image.csize;
    m_buffer.format=image.format;

    set=true;
    set_zero=true;
  }

  // set!(if needed)
  if (set){
    if (set_zero)m_buffer.setBlack();
    else{
      j=m_bufnum;
      while(j--){
	source=image.data;
	dest=m_buffer.data+j*imagesize;
	i=imagesize;while(i--)*dest++=*source++;
      }
    }
    set=false;
    set_zero=false;
  }  

  // do the filtering
  // feed-back
  f=m_fb[0];
  source=image.data;
  dest=m_buffer.data+m_counter*imagesize;
  int factor=(int)(f*255);
  i=imagesize;while(i--)*dest++ = (unsigned char)((factor**source++)>>8);
  j=fb_count;while(j--){
    f=m_fb[j+1];
    source=m_buffer.data+imagesize*((m_bufnum+m_counter-j-1)%m_bufnum);
    dest=m_buffer.data+m_counter*imagesize;
    factor=(int)(255*f);
    if (factor!=0){
      i=imagesize;while(i--)*dest++ += (unsigned char)((factor**source++)>>8);
    }
  }

  // feed-forward
  f=m_ff[0];
  source=m_buffer.data+m_counter*imagesize;
  dest=image.data;
  factor=(int)(f*255);
  i=imagesize;while(i--)*dest++ = (unsigned char)((factor**source++)>>8);
  j=ff_count;while(j--){
    f=m_ff[j+1];
    dest=image.data;
    source=m_buffer.data+imagesize*((m_bufnum+m_counter-j-1)%m_bufnum);
    factor=(int)(f*255);
    if (factor!=0){
      i=imagesize;while(i--)*dest++ += (unsigned char)((factor**source++)>>8);
    }
  }

  m_counter++;
  m_counter%=m_bufnum;
}

/////////////////////////////////////////////////////////
// static member function
//
/////////////////////////////////////////////////////////
void pix_tIIR :: obj_setupCallback(t_class *classPtr)
{
  class_addmethod(classPtr, (t_method)&pix_tIIR::setMessCallback,
		  gensym("set"), A_GIMME, A_NULL);
}

void pix_tIIR :: setMessCallback(void *data, t_symbol *s, int argc, t_atom* argv)
{
  GetMyClass(data)->set = true;
  GetMyClass(data)->set_zero = (argc>0 && atom_getint(argv)==0);
  
}
